<?php
class DAO_Container extends C4_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const DESCRIPTION = 'description';
	const ENABLED = 'enabled';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "INSERT INTO container () VALUES ()";
		$db->Execute($sql);
		$id = $db->LastInsertId();

		self::update($id, $fields);

		return $id;
	}

	static function update($ids, $fields) {
		parent::_update($ids, 'container', $fields);
	}

	static function updateWhere($fields, $where) {
		parent::_updateWhere('container', $fields, $where);
	}

	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Container[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);

		// SQL
		$sql = "SELECT id, name, description, enabled ".
			"FROM container ".
		$where_sql.
		$sort_sql.
		$limit_sql
		;
		$rs = $db->Execute($sql);

		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_Container	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));

		if(isset($objects[$id]))
			return $objects[$id];

		return null;
	}
	
	/**
	* @param int $number
	* @return Model_Container[]
	*/
	static function getByNumber($context, $source_id, $user_id) {
		if(null !== $container_link = DAO_ContainerLink::getByNumber($context, $source_id, $user_id)) {
			if(null !== $container = DAO_Container::get($container_link->container_id)) {
				return $container;
			}
		}
	
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Container[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();

		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_Container();
			$object->id = $row['id'];
			$object->name = $row['name'];
			$object->description = $row['description'];
			$object->enabled = $row['enabled'];
			$objects[$object->id] = $object;
		}

		mysql_free_result($rs);

		return $objects;
	}

	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();

		if(empty($ids))
		return;

		$ids_list = implode(',', $ids);

		$db->Execute(sprintf("DELETE FROM container WHERE id IN (%s)", $ids_list));

		// Fire event
		/*
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
		new Model_DevblocksEvent(
		'context.delete',
		array(
		'context' => 'cerberusweb.contexts.',
		'context_ids' => $ids
		)
		)
		);
		*/

		return true;
	}

	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Container::getFields();

		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
		$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);

		$select_sql = sprintf("SELECT ".
			"container.id as %s, ".
			"container.name as %s, ".
			"container.description as %s, ".
			"container.enabled as %s ",
			SearchFields_Container::ID,
			SearchFields_Container::NAME,
			SearchFields_Container::DESCRIPTION,
			SearchFields_Container::ENABLED
		);
			
		$join_sql = "FROM container ";

		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'container.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled

		$where_sql = "".
		(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";

		return array(
			'primary_table' => 'container',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}

	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];

		$sql =
		$select_sql.
		$join_sql.
		$where_sql.
		($has_multiple_values ? 'GROUP BY container.id ' : '').
		$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
			$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
			$total = mysql_num_rows($rs);
		}

		$results = array();
		$total = -1;

		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($row[SearchFields_Container::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
			($has_multiple_values ? "SELECT COUNT(DISTINCT container.id) " : "SELECT COUNT(container.id) ").
			$join_sql.
			$where_sql;
			$total = $db->GetOne($count_sql);
		}

		mysql_free_result($rs);

		return array($results,$total);
	}

};

class SearchFields_Container implements IDevblocksSearchFields {
	const ID = 'c_id';
	const NAME = 'c_name';
	const DESCRIPTION = 'c_description';
	const ENABLED = 'c_enabled';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'container', 'id', $translate->_('container.id')),
			self::NAME => new DevblocksSearchField(self::NAME, 'container', 'name', $translate->_('container.name')),
			self::DESCRIPTION => new DevblocksSearchField(self::DESCRIPTION, 'container', 'description', $translate->_('container.description')),
			self::ENABLED => new DevblocksSearchField(self::ENABLED, 'container', 'enabled', $translate->_('container.enabled')),
		);

		// Custom Fields
		//$fields = DAO_CustomField::getByContext(CerberusContexts::XXX);

		//if(is_array($fields))
		//foreach($fields as $field_id => $field) {
		//	$key = 'cf_'.$field_id;
		//	$columns[$key] = new DevblocksSearchField($key,$key,'field_value',$field->name);
		//}

		// Sort by label (translation-conscious)
		uasort($columns, create_function('$a, $b', "return strcasecmp(\$a->db_label,\$b->db_label);\n"));

		return $columns;
	}
};

class Model_Container {
	public $id;
	public $name;
	public $description;
	public $enabled;
};

class View_Container extends C4_AbstractView {
	const DEFAULT_ID = 'container';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();

		$this->id = self::DEFAULT_ID;
		// [TODO] Name the worklist view
		$this->name = $translate->_('Container');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Container::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Container::ID,
			SearchFields_Container::NAME,
			SearchFields_Container::DESCRIPTION,
			SearchFields_Container::ENABLED,
		);
		// [TODO] Filter fields
		$this->addColumnsHidden(array(
		));

		// [TODO] Filter fields
		$this->addParamsHidden(array(
		));

		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_Container::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}

	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_Container', $size);
	}

	function render() {
		$this->_sanitize();

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		//$custom_fields = DAO_CustomField::getByContext(CerberusContexts::XXX);
		//$tpl->assign('custom_fields', $custom_fields);

		// [TODO] Set your template path
		$tpl->display('devblocks:example.plugin::path/to/view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		// [TODO] Move the fields into the proper data type
		switch($field) {
			case SearchFields_Container::ID:
			case SearchFields_Container::NAME:
			case SearchFields_Container::DESCRIPTION:
			case SearchFields_Container::ENABLED:
			case 'placeholder_string':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
			case 'placeholder_number':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
			case 'placeholder_date':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				/*
				 default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
				$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
				echo ' ';
				}
				break;
				*/
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
			break;
		}
	}

	function getFields() {
		return SearchFields_Container::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		// [TODO] Move fields into the right data type
		switch($field) {
			case SearchFields_Container::ID:
			case SearchFields_Container::NAME:
			case SearchFields_Container::DESCRIPTION:
			case SearchFields_Container::ENABLED:
			case 'placeholder_string':
				// force wildcards if none used on a LIKE
				if(($oper == DevblocksSearchCriteria::OPER_LIKE || $oper == DevblocksSearchCriteria::OPER_NOT_LIKE)
				&& false === (strpos($value,'*'))) {
					$value = $value.'*';
				}
				$criteria = new DevblocksSearchCriteria($field, $oper, $value);
				break;
			case 'placeholder_number':
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;

			case 'placeholder_date':
				@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','');
				@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');

				if(empty($from)) $from = 0;
				if(empty($to)) $to = 'today';

				$criteria = new DevblocksSearchCriteria($field,$oper,array($from,$to));
				break;

			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;

				/*
				 default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
				$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
				*/
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}

	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m

		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
		return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
		return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_Container::EXAMPLE] = 'some value';
					break;
					/*
					 default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
					$custom_fields[substr($k,3)] = $v;
					}
					break;
					*/
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_Container::search(
			array(),
			$this->getParams(),
			100,
			$pg++,
			SearchFields_Container::ID,
			true,
			false
			);
			$ids = array_merge($ids, array_keys($objects));

		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
				
			DAO_Container::update($batch_ids, $change_fields);
	
			// Custom Fields
			//self::_doBulkSetCustomFields(ChCustomFieldSource_Container::ID, $custom_fields, $batch_ids);
				
			unset($batch_ids);
		}

		unset($ids);
	}
};

class DAO_ContainerLink extends C4_ORMHelper {
	const CONTAINER_ID = 'container_id';
	const CONTEXT = 'context';
	const SOURCE_ID = 'source_id';
	const USER_ID = 'user_id';

	static function create($container_id, $context, $source_id, $user_id) {
		$db = DevblocksPlatform::getDatabaseService();
		$db->Execute(sprintf("INSERT IGNORE INTO container_link (container_id, context, source_id, user_id) ".
			"VALUES (%d, %s, %d, %d)",
			$container_id,
			$db->qstr($context),
			$source_id,
			$user_id
		));
	}
	
	/**
	 * @param string $where
	 * @return Model_ContainerLink[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "SELECT container_id, context, source_id, user_id ".
			"FROM container_link ".
		(!empty($where) ? sprintf("WHERE %s ",$where) : "");
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	* @param string $context
	* @param int $source_id
	* @param int $container_id
	* @return Model_ContainerLink[]
	*/
	
	static function getByContainerId($container_id) {
		$db = DevblocksPlatform::getDatabaseService();
	
		$objects = self::getWhere(sprintf("%s = %d",
			self::CONTAINER_ID,
			$container_id
		));
		
		if(isset($objects[$container_id]))
			return $objects[$container_id];
		
		return null;
	}
	
	/**
	 * @param string $context
	 * @param int $source_id
	 * @param int $container_id
	 * @return Model_ContainerLink[]
	 */

	static function getByNumber($context, $source_id, $user_id) {
		$db = DevblocksPlatform::getDatabaseService();

		return array_shift(self::getWhere(sprintf("%s = %s AND %s = %d AND %s = %d",
			self::CONTEXT,
			$db->qstr($context),
			self::SOURCE_ID,
			$source_id,
			self::USER_ID,
			$user_id
		)));
	}

	/**
	* @param string $context
	* @return Model_ContainerLink[]
	*/
	
	static function getByContext($context) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$objects = self::getWhere(sprintf("%s = %s",
			self::CONTEXT,
			$db->qstr($context)
		));
		
		if(!empty($objects)) {
			return $objects;
		}
		
		return null;
	}
	
	
	/**
	 * @param resource $rs
	 * @return Model_ContainerLink[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();

		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_ContainerLink();
			$object->container_id = $row['container_id'];
			$object->context = $row['context'];
			$object->source_id = $row['source_id'];
			$object->user_id = $row['user_id'];
			$objects[$object->container_id] = $object;
		}

		mysql_free_result($rs);

		return $objects;
	}

	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();

		if(empty($ids))
		return;

		$ids_list = implode(',', $ids);

		$db->Execute(sprintf("DELETE FROM container_link WHERE id IN (%s)", $ids_list));

		// Fire event
		/*
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
		new Model_DevblocksEvent(
		'context.delete',
		array(
		'context' => 'cerberusweb.contexts.',
		'context_ids' => $ids
		)
		)
		);
		*/

		return true;
	}

	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_ContainerLink::getFields();

		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
		$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);

		$select_sql = sprintf("SELECT ".
			"container_link.container_id as %s, ".
			"container_link.source_context as %s, ".
			"container_link.source_id as %s, ".
			"container_link.user_id as %s ",
			SearchFields_ContainerLink::CONTAINER_ID,
			SearchFields_ContainerLink::CONTEXT,
			SearchFields_ContainerLink::SOURCE_ID,
			SearchFields_ContainerLink::USER_ID
		);
			
		$join_sql = "FROM container_link ";

		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'container_link.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled

		$where_sql = "".
		(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";

		return array(
			'primary_table' => 'container_link',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}

	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];

		$sql =
		$select_sql.
		$join_sql.
		$where_sql.
		($has_multiple_values ? 'GROUP BY container_link.id ' : '').
		$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
			$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
			$total = mysql_num_rows($rs);
		}

		$results = array();
		$total = -1;

		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($row[SearchFields_ContainerLink::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
			($has_multiple_values ? "SELECT COUNT(DISTINCT container_link.id) " : "SELECT COUNT(container_link.id) ").
			$join_sql.
			$where_sql;
			$total = $db->GetOne($count_sql);
		}

		mysql_free_result($rs);

		return array($results,$total);
	}

};

class SearchFields_ContainerLink implements IDevblocksSearchFields {
	const CONTAINER_ID = 'c_container_id';
	const CONTEXT = '_context';
	const SOURCE_ID = 'i_source_id';
	const USER_ID = 'i_user_id';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::CONTAINER_ID => new DevblocksSearchField(self::CONTAINER_ID, 'container_link', 'container_id', $translate->_('container_link.container_id')),
			self::CONTEXT => new DevblocksSearchField(self::CONTEXT, 'container_link', 'context', $translate->_('container_link.context')),
			self::SOURCE_ID => new DevblocksSearchField(self::SOURCE_ID, 'container_link', 'source_id', $translate->_('container_link.source_id')),
			self::USER_ID => new DevblocksSearchField(self::USER_ID, 'container_link', 'user_id', $translate->_('container_link.user_id')),
		);

		// Custom Fields
		//$fields = DAO_CustomField::getByContext(CerberusContexts::XXX);

		//if(is_array($fields))
		//foreach($fields as $field_id => $field) {
		//	$key = 'cf_'.$field_id;
		//	$columns[$key] = new DevblocksSearchField($key,$key,'field_value',$field->name);
		//}

		// Sort by label (translation-conscious)
		uasort($columns, create_function('$a, $b', "return strcasecmp(\$a->db_label,\$b->db_label);\n"));

		return $columns;
	}
};


class Model_ContainerLink {
	public $container_id;
	public $context;
	public $source_id;
	public $user_id;
	
	public function getContainer() {
		return DAO_Container::get($this->container_id);
	}
	
}