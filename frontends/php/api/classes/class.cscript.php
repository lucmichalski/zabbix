<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php

/**
 * Class containing methods for operations with Scripts
 * @package API
 */
class CScript extends CZBXAPI {

	protected $tableName = 'scripts';
	protected $tableAlias = 's';

	/**
	 * Get Scripts data
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids'] - depricated (very slow)
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['scriptids']
	 * @param boolean $options['status']
	 * @param boolean $options['editable']
	 * @param boolean $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 * @return array|int item data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('scriptid', 'name');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('scripts' => 's.scriptid'),
			'from'		=> array('scripts s'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'scriptids'				=> null,
			'usrgrpids'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
			// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,
			// output
			'output'				=> API_OUTPUT_REFER,
			'selectGroups'			=> null,
			'selectHosts'			=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['scripts']);

			$dbTable = DB::getSchema('scripts');
			$sqlParts['select']['scriptid'] = 's.scriptid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 's.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + permission check
		if (USER_TYPE_SUPER_ADMIN == $userType) {
		}
		elseif (!is_null($options['editable'])) {
			return $result;
		}
		else {
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = 'hg.groupid=r.id';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = '(hg.groupid=s.groupid OR s.groupid IS NULL)';
			$sqlParts['where'][] = '(ug.usrgrpid=s.usrgrpid OR s.usrgrpid IS NULL)';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			$options['groupids'][] = 0; // include all groups scripts

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['scripts'] = 's.scriptid,s.groupid';
			}
			$sqlParts['where'][] = '('.DBcondition('s.groupid', $options['groupids']).' OR s.groupid IS NULL)';
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);
			$options['usrgrpids'][] = 0; // include all usrgrps scripts

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['usrgrpid'] = 's.usrgrpid';
			}
			$sqlParts['where'][] = '('.DBcondition('s.usrgrpid', $options['usrgrpids']).' OR s.usrgrpid IS NULL)';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['hostid'] = 'hg.hostid';
			}
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = '(('.DBcondition('hg.hostid', $options['hostids']).' AND hg.groupid=s.groupid)'.
									' OR '.
									'(s.groupid IS NULL))';
		}

		// scriptids
		if (!is_null($options['scriptids'])) {
			zbx_value2array($options['scriptids']);

			$sqlParts['where'][] = DBcondition('s.scriptid', $options['scriptids']);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['scripts'] = 's.*';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('scripts s', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('scripts s', $options, $sqlParts);
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';

			$sqlParts['select'] = array('count(DISTINCT s.scriptid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 's');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$scriptids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= ' AND '.implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.DBin_node('s.scriptid', $nodeids).
					$sqlWhere.
					$sqlOrder;
		$res = DBselect($sql, $sqlLimit);
		while ($script = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $script['rowscount'];
			}
			else {
				$scriptids[$script['scriptid']] = $script['scriptid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$script['scriptid']] = array('scriptid' => $script['scriptid']);
				}
				else {
					if (!isset($result[$script['scriptid']])) {
						$result[$script['scriptid']] = array();
					}
					if (!is_null($options['selectGroups']) && !isset($result[$script['scriptid']]['groups'])) {
						$result[$script['scriptid']]['groups'] = array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$script['scriptid']]['hosts'])) {
						$result[$script['scriptid']]['hosts'] = array();
					}

					$result[$script['scriptid']] += $script;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding groups
		if (!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselectsAllowedOutputs)) {
			foreach ($result as $scriptid => $script) {
				$result[$scriptid]['groups'] = API::HostGroup()->get(array(
					'output' => $options['selectGroups'],
					'groupids' => ($script['groupid']) ? $script['groupid'] : null,
					'editable' => ($script['host_access'] == PERM_READ_WRITE) ? true : null
				));
			}
		}

		// adding hosts
		if (!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
			foreach ($result as $scriptid => $script) {
				$result[$scriptid]['hosts'] = API::Host()->get(array(
					'output' => $options['selectHosts'],
					'groupids' => ($script['groupid']) ? $script['groupid'] : null,
					'editable' => ($script['host_access'] == PERM_READ_WRITE) ? true : null,
					'nodeids' => id2nodeid($script['scriptid'])
				));
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Get Script ID by host.name and item.key
	 *
	 * @param array $script
	 * @param array $script['name']
	 * @param array $script['hostid']
	 * @return int|boolean
	 */
	public function getObjects($script) {
		$result = array();
		$scriptids = array();

		$dbScripts = DBselect(
			'SELECT s.scriptid'.
			' FROM scripts s'.
			' WHERE '.DBin_node('s.scriptid').
				' AND s.name='.$script['name']
		);
		while ($script = DBfetch($dbScripts)) {
			$scriptids[$script['scriptid']] = $script['scriptid'];
		}

		if (!empty($scriptids)) {
			$result = $this->get(array('scriptids' => $scriptids, 'output' => API_OUTPUT_EXTEND));
		}
		return $result;
	}

	private function _clearData(&$scripts) {
		foreach ($scripts as $snum => $script) {
			if (isset($script['type']) && $script['type'] == ZBX_SCRIPT_TYPE_IPMI) {
				unset($scripts[$snum]['execute_on']);
			}
		}
	}

	/**
	 * Add Scripts
	 *
	 * @param _array $scripts
	 * @param array $script['name']
	 * @param array $script['hostid']
	 * @return boolean
	 */
	public function create($scripts) {
		$scripts = zbx_toArray($scripts);

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}

		$scriptNames = array();
		foreach ($scripts as $script) {
			$scriptDbFields = array(
				'name' => null,
				'command' => null
			);
			if (!check_db_fields($scriptDbFields, $script)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for script'));
			}

			if (isset($scriptNames[$script['name']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate script name "%s".', $script['name']));
			}

			$scriptNames[$script['name']] = $script['name'];
		}

		$scriptsDB = $this->get(array(
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'filter' => array('name' => $scriptNames),
			'limit' => 1
		));
		if ($exScript = reset($scriptsDB)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script "%s" already exists.', $exScript['name']));
		}

		$this->_clearData($scripts);
		$scriptids = DB::insert('scripts', $scripts);

		return array('scriptids' => $scriptids);
	}

	/**
	 * Update Scripts
	 *
	 * @param _array $scripts
	 * @param array $script['name']
	 * @param array $script['hostid']
	 * @return boolean
	 */
	public function update($scripts) {
		$scripts = zbx_toHash($scripts, 'scriptid');
		$scriptids = array_keys($scripts);

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}

		$updScripts = $this->get(array(
			'scriptids' => $scriptids,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		));
		$scriptNames = array();
		foreach ($scripts as $script) {
			if (!isset($updScripts[$script['scriptid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script with scriptid "%s" does not exist.', $script['scriptid']));
			}

			if (isset($script['name'])) {
				if (isset($scriptNames[$script['name']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate script name "%s".', $script['name']));
				}
				$scriptNames[$script['name']] = $script['name'];
			}
		}

		if (!empty($scriptNames)) {
			$dbScripts = $this->get(array(
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'filter' => array('name' => $scriptNames)
			));
			foreach ($dbScripts as $exScript) {
				if (!isset($scripts[$exScript['scriptid']]) || (bccomp($scripts[$exScript['scriptid']]['scriptid'], $exScript['scriptid']) != 0)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script "%s" already exists.', $exScript['name']));
				}
			}
		}

		$this->_clearData($scripts);
		$update = array();
		foreach ($scripts as $script) {
			$scriptid = $script['scriptid'];
			unset($script['scriptid']);
			$update[] = array(
				'values' => $script,
				'where' => array('scriptid'=>$scriptid),
			);
		}
		DB::update('scripts', $update);

		return array('scriptids' => $scriptids);
	}

	/**
	 * Delete Scripts
	 *
	 * @param _array $scriptids
	 * @param array $scriptids
	 * @return boolean
	 */
	public function delete($scriptids) {
		$scriptids = zbx_toArray($scriptids);

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}

		if (empty($scriptids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete scripts. Empty input parameter "scriptids".'));
		}

		$dbScripts = $this->get(array(
			'scriptids' => $scriptids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($scriptids as $scriptid) {
			if (isset($dbScripts[$scriptid])) {
				continue;
			}
			self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot delete scripts. Script with scriptid "%s" does not exist.', $scriptid));
		}

		$scriptActions = API::Action()->get(array(
			'scriptids' => $scriptids,
			'nopermissions' => true,
			'preservekeys' => true,
			'output' => array('actionid','name')
		));

		foreach ($scriptActions as $action) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete scripts. Script "%1$s" is used in action operation "%2$s".',
				$dbScripts[$action['scriptid']]['name'], $action['name']));
		}

		DB::delete('scripts', array('scriptid' => $scriptids));

		return array('scriptids' => $scriptids);
	}

	public function execute($data) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT, $ZBX_MESSAGES;

		$scriptid = $data['scriptid'];
		$hostid = $data['hostid'];

		$options = array(
			'hostids' => $hostid,
			'scriptids' => $scriptid,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		);
		$alowedScripts = $this->get($options);
		if (!isset($alowedScripts[$scriptid])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}
		if (!$socket = fsockopen($ZBX_SERVER, $ZBX_SERVER_PORT, $errorCode, $errorMsg, ZBX_SCRIPT_TIMEOUT)) {
			// remove warnings generated by fsockopen
			foreach ($ZBX_MESSAGES as $arrayNum => $fsockErrorCheck) {
				foreach ($fsockErrorCheck as $key => $value) {
					if ($key == 'message' && strpos($value, 'fsockopen()') !== false) {
						unset($ZBX_MESSAGES[$arrayNum]);
					}
				}
			}

			switch ($errorMsg) {
				case 'Connection refused':
					$dErrorMsg = _s('Connection to Zabbix server "%s" refused. Possible reasons: 1. Incorrect server IP/DNS in the "zabbix.conf.php"; 2. Security environment (for example, SELinux) is blocking the connection; 3. Zabbix server daemon not running; 4. Firewall is blocking TCP connection.', $ZBX_SERVER).SPACE;
					break;
				case 'No route to host':
					$dErrorMsg = _s('Zabbix server "%s" can not be reached. Possible reasons: 1. Incorrect server IP/DNS in the "zabbix.conf.php"; 2. Incorrect network configuration.', $ZBX_SERVER).SPACE;
					break;
				case 'Connection timed out':
					$dErrorMsg = _s('Connection to Zabbix server "%s" timed out. Possible reasons: 1. Incorrect server IP/DNS in the "zabbix.conf.php"; 2. Firewall is blocking TCP connection.', $ZBX_SERVER).SPACE;
					break;
				case 'php_network_getaddresses: getaddrinfo failed: Name or service not known':
					$dErrorMsg = _s('Connection to Zabbix server "%s" faileds. Possible reasons: 1. Incorrect server IP/DNS in the "zabbix.conf.php"; 2. Incorrect DNS server configuration.', $ZBX_SERVER).SPACE;
					break;
				default:
					$dErrorMsg = '';
			}
			self::exception(ZBX_API_ERROR_INTERNAL, $dErrorMsg._('Error description').': '.$errorMsg);
		}

		$json = new CJSON();
		$array = array(
			'request' => 'command',
			'nodeid' => id2nodeid($hostid),
			'scriptid' => $scriptid,
			'hostid' => $hostid
		);
		$dataToSend = $json->encode($array, false);

		stream_set_timeout($socket, ZBX_SCRIPT_TIMEOUT);

		if (fwrite($socket, $dataToSend) === false) {
			self::exception(ZBX_API_ERROR_INTERNAL, _('Error description: can\'t send command, check connection.'));
		}

		$response = '';

		$pbl = ZBX_SCRIPT_BYTES_LIMIT > 8192 ? 8192 : ZBX_SCRIPT_BYTES_LIMIT; // PHP read bytes limit
		$now = time();
		$i = 0;
		while (!feof($socket)) {
			$i++;
			if ((time() - $now) >= ZBX_SCRIPT_TIMEOUT) {
				self::exception(ZBX_API_ERROR_INTERNAL,
					_('Error description: defined in "include/defines.inc.php" constant ZBX_SCRIPT_TIMEOUT timeout is reached. You can try to increase this value.'));
			}
			elseif (($i * $pbl) >= ZBX_SCRIPT_BYTES_LIMIT) {
				self::exception(ZBX_API_ERROR_INTERNAL,
					_('Error description: defined in "include/defines.inc.php" constant ZBX_SCRIPT_BYTES_LIMIT read bytes limit is reached. You can try to increase this value.'));
			}

			if (($out = fread($socket, $pbl)) !== false) {
				$response .= $out;
			}
			else {
				self::exception(ZBX_API_ERROR_INTERNAL, _('Error description: can\'t read script response, check connection.'));
			}
		}

		if (strlen($response) > 0) {
			$rcv = $json->decode($response, true);
		}
		else {
			self::exception(ZBX_API_ERROR_INTERNAL, _('Error description: empty response received.'));
		}
		fclose($socket);
		return $rcv;
	}

	public function getScriptsByHosts($hostids) {
		zbx_value2array($hostids);

		$hostsReadOnly  = API::Host()->get(array(
			'hostids' => $hostids,
			'preservekeys' => true
		));
		$hostsReadOnly = zbx_objectValues($hostsReadOnly, 'hostid');

		$hostsReadWrite = API::Host()->get(array(
			'editable' => true,
			'hostids' => $hostids,
			'preservekeys' => true
		));
		$hostsReadWrite = zbx_objectValues($hostsReadWrite, 'hostid');

		$scriptsByHost = array();
		foreach ($hostids as $id => $hostid) {
			$scriptsByHost[$hostid] = array();
		}

		$groups = API::HostGroup()->get(array(
			'hostids' => $hostids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$scripts  = API::Script()->get(array(
			'groupids' => zbx_objectValues($groups, 'groupid'),
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => 'name',
			'preservekeys' => true
		));
		foreach ($scripts as $script) {
			$addToHosts = array();
			$hostids = zbx_objectValues($groups[$script['groupid']]['hosts'], 'hostid');

			if (PERM_READ_WRITE == $script['host_access']) {
				if ($script['groupid'] > 0) {
					$addToHosts = zbx_uint_array_intersect($hostsReadWrite, $hostids);
				}
				else {
					$addToHosts = $hostsReadWrite;
				}
			}
			elseif (PERM_READ_ONLY == $script['host_access']) {
				if ($script['groupid'] > 0) {
					$addToHosts = zbx_uint_array_intersect($hostsReadOnly, $hostids);
				}
				else {
					$addToHosts = $hostsReadOnly;
				}
			}

			foreach ($addToHosts as $id => $hostid) {
				$scriptsByHost[$hostid][] = $script;
			}
		}
		return $scriptsByHost;
	}
}
?>
