<?php
class DAO_Milestone extends C4_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const DESCRIPTION = 'description';
	const STATE = 'state';
	const CREATED_DATE = 'created_date';
	const DUE_DATE = 'due_date';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "INSERT INTO milestone () VALUES ()";
		$db->Execute($sql);
		$id = $db->LastInsertId();

		self::update($id, $fields);

		return $id;
	}

	static function update($ids, $fields) {
		parent::_update($ids, 'milestone', $fields);
	}

	static function updateWhere($fields, $where) {
		parent::_updateWhere('milestone', $fields, $where);
	}
	
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Milestone[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);

		// SQL
		$sql = "SELECT id, name, description, state, created_date, due_date ".
			"FROM milestone ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_Milestone
	 */
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
	* @return Model_Milestone[]
	*/
	static function getByNumber($context, $source_id, $user_id) {
		if(null !== $milestone_link = DAO_MilestoneLink::getByNumber($context, $source_id, $user_id)) {
			return $milestone_link->getMilestone();
		}
	
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Milestone[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();

		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_Milestone();
			$object->id = $row['id'];
			$object->name = $row['name'];
			$object->description = $row['description'];
			$object->state = $row['state'];
			$object->created_date = $row['created_date'];
			$object->due_date = $row['due_date'];
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

		$db->Execute(sprintf("DELETE FROM milestone WHERE id IN (%s)", $ids_list));

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
		$fields = SearchFields_Milestone::getFields();

		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
		$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);

		$select_sql = sprintf("SELECT ".
			"milestone.id as %s, ".
			"milestone.name as %s, ".
			"milestone.description as %s, ".
			"milestone.state as %s, ".
			"milestone.created_date as %s, ".
			"milestone.due_date as %s ",
			SearchFields_Milestone::ID,
			SearchFields_Milestone::NAME,
			SearchFields_Milestone::DESCRIPTION,
			SearchFields_Milestone::STATE,
			SearchFields_Milestone::CREATED_DATE,
			SearchFields_Milestone::DUE_DATE
		);
			
		$join_sql = "FROM milestone ";

		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'milestone.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled

		$where_sql = "".
		(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";

		return array(
			'primary_table' => 'milestone',
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
		($has_multiple_values ? 'GROUP BY milestone.id ' : '').
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
			$object_id = intval($row[SearchFields_Milestone::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
			($has_multiple_values ? "SELECT COUNT(DISTINCT milestone.id) " : "SELECT COUNT(milestone.id) ").
			$join_sql.
			$where_sql;
			$total = $db->GetOne($count_sql);
		}

		mysql_free_result($rs);

		return array($results,$total);
	}

};

class SearchFields_Milestone implements IDevblocksSearchFields {
	const ID = 'gm_id';
	const NAME = 'gm_name';
	const DESCRIPTION = 'gm_description';
	const STATE = 'gm_state';
	const CREATED_DATE = 'gm_created_date';
	const DUE_DATE = 'gm_due_date';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'milestone', 'id', $translate->_('milestone.id')),
			self::NAME => new DevblocksSearchField(self::NAME, 'milestone', 'name', $translate->_('milestone.name')),
			self::DESCRIPTION => new DevblocksSearchField(self::DESCRIPTION, 'milestone', 'description', $translate->_('milestone.description')),
			self::STATE => new DevblocksSearchField(self::STATE, 'milestone', 'state', $translate->_('milestone.state')),
			self::CREATED_DATE => new DevblocksSearchField(self::CREATED_DATE, 'milestone', 'created_date', $translate->_('milestone.created_date')),
			self::DUE_DATE => new DevblocksSearchField(self::DUE_DATE, 'milestone', 'due_date', $translate->_('milestone.due_date')),
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

class Model_Milestone {
	public $id;
	public $name;
	public $description;
	public $state;
	public $created_date;
	public $due_date;
};

class View_Milestone extends C4_AbstractView {
	const DEFAULT_ID = 'milestone';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();

		$this->id = self::DEFAULT_ID;
		// [TODO] Name the worklist view
		$this->name = $translate->_('GithubMilestone');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Milestone::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Milestone::ID,
			SearchFields_Milestone::NAME,
			SearchFields_Milestone::DESCRIPTION,
			SearchFields_Milestone::STATE,
			SearchFields_Milestone::CREATED_DATE,
			SearchFields_Milestone::DUE_DATE,
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
		$objects = DAO_Milestone::search(
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
		return $this->_doGetDataSample('DAO_Milestone', $size);
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
			case SearchFields_Milestone::ID:
			case SearchFields_Milestone::NAME:
			case SearchFields_Milestone::DESCRIPTION:
			case SearchFields_Milestone::STATE:
			case SearchFields_Milestone::CREATED_DATE:
			case SearchFields_Milestone::DUE_DATE:
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
		return SearchFields_Milestone::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		// [TODO] Move fields into the right data type
		switch($field) {
			case SearchFields_Milestone::ID:
			case SearchFields_Milestone::NAME:
			case SearchFields_Milestone::DESCRIPTION:
			case SearchFields_Milestone::STATE:
			case SearchFields_Milestone::CREATED_DATE:
			case SearchFields_Milestone::DUE_DATE:
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
					//$change_fields[DAO_Milestone::EXAMPLE] = 'some value';
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
			list($objects,$null) = DAO_Milestone::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Milestone::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));

		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
				
			DAO_Milestone::update($batch_ids, $change_fields);
	
			// Custom Fields
			//self::_doBulkSetCustomFields(ChCustomFieldSource_Milestone::ID, $custom_fields, $batch_ids);
				
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Milestone extends Extension_DevblocksContext {
	function authorize($context_id, Model_Worker $worker) {
		return TRUE;
	}

	function getMeta($context_id) {
		$milestone = DAO_Milestone::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();

		$friendly = DevblocksPlatform::strToPermalink($milestone->name);

		return array(
			'id' => $miltestone->id,
			'name' => $milestone->name,
			'permalink' => $url_writer->writeNoProxy(sprintf("c=github&action=milestones&id=%d-%s", $milestone->id, $friendly, true)),
		);
	}

	function getContext($milestone, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
		$prefix = 'GithubMilestone:';

		$translate = DevblocksPlatform::getTranslationService();

		// Polymorph
		if(is_numeric($milestone)) {
			$milestone = DAO_Milestone::get($milestone);
		} elseif($milestone instanceof Model_Milestone) {
			// It's what we want already.
		} else {
			$article = null;
		}
		/* @var $article Model_Milestone */
			
		// Token labels
		$token_labels = array(
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('milestone.name'),
			'description' => $prefix.$translate->_('milestone.description'),
			'created_date|date' => $prefix.$translate->_('milestone.created_date'),
			'due_date|date' => $prefix.$translate->_('milestone.due_date'),
			'state' => $prefix.$translate->_('milestone.state'),
		);

		// Token values
		$token_values = array();

		// Token values
		if(null != $article) {
			$token_values['id'] = $milestone->id;
			$token_values['name'] = $milestone->name;
			$token_values['description'] = $milestone->description;
			$token_values['created_date'] = $milestone->created_date;
			$token_values['due_date'] = $milestone->due_date;
			$token_values['state'] = $milestone->state;

			// Milestones
			if(null != ($categories = $article->getCategories()) && is_array($categories)) {
				$token_values['categories'] = array();

				foreach($categories as $category_id => $trail) {
					foreach($trail as $step_id => $step) {
						if(!isset($token_values['categories'][$category_id]))
						$token_values['categories'][$category_id] = array();
						$token_values['categories'][$category_id][$step_id] = $step->name;
					}
				}
			}

			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=github&action=milestones&id=%d-%s", $milestone->id, DevblocksPlatform::strToPermalink($milestone->name)), true);
		}

		return TRUE;
	}

	function getChooserView() {
		$active_worker = CerberusApplication::getActiveWorker();

		// View
		$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);

		$view->addParams(array(
		SearchFields_Milestone::STATE => new DevblocksSearchCriteria(SearchFields_Milestone::STATE,'!=','closed'),
		), true);
		$view->renderSortBy = SearchFields_Milestone::UPDATED_DATE;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';

		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}

	function getView($context=null, $context_id=null, $options=array()) {
		$view_id = str_replace('.','_',$this->id);

		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		//$view->name = 'Calls';

		$params_req = array();

		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Milestone::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Milestone::CONTEXT_LINK_ID,'=',$context_id),
			);
		}

		$view->addParamsRequired($params_req, true);

		$view->renderTemplate = 'context';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
};

class DAO_MilestoneLink extends C4_ORMHelper {
	const MILESTONE_ID = 'milestone_id';
	const CONTEXT = 'context';
	const SOURCE_ID = 'source_id';
	const CONTAINER_ID = 'container_id';

	static function create($milestone_id, $context, $source_id, $container_id) {
		$db = DevblocksPlatform::getDatabaseService();
		$db->Execute(sprintf("INSERT IGNORE INTO milestone_link (milestone_id, context, source_id, container_id) ".
			"VALUES (%d, %s, %d, %d)",
			$milestone_id,
			$db->qstr($context),
			$source_id,
			$container_id
		));
	}

	/**
	 * @param string $where
	 * @return Model_MilestoneLink[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "SELECT milestone_id, context, source_id, container_id ".
			"FROM milestone_link ".
		(!empty($where) ? sprintf("WHERE %s ",$where) : "");
		$rs = $db->Execute($sql);

		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param string $context
	 * @param int $source_id
	 * @param int $milestone_id
	 * @return Model_MilestoneLink[]
	 */

	static function getByMilestoneId($milestone_id) {
		$db = DevblocksPlatform::getDatabaseService();

		$objects = self::getWhere(sprintf("%s = %d",
			self::MILESTONE_ID,
			$milestone_id
		));

		if(isset($objects[$milestone_id]))
			return $objects[$milestone_id];

		return null;
	}

	/**
	 * @param string $context
	 * @param int $source_id
	 * @param int $container_id
	 * @return Model_MilestoneLink[]
	 */

	static function getByNumber($context, $source_id, $container_id) {
		$db = DevblocksPlatform::getDatabaseService();

		return array_shift(self::getWhere(sprintf("%s = %s AND %s = %d AND %s = %d",
			self::CONTEXT,
			$db->qstr($context),
			self::SOURCE_ID,
			$source_id,
			self::CONTAINER_ID,
			$container_id
		)));
	}

	/**
	 * @param string $context
	 * @return Model_MilestoneLink[]
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
	 * @return Model_MilestoneLink[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();

		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_MilestoneLink();
			$object->milestone_id = $row['milestone_id'];
			$object->context = $row['context'];
			$object->source_id = $row['source_id'];
			$object->container_id = $row['container_id'];
			$objects[$object->milestone_id] = $object;
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

		$db->Execute(sprintf("DELETE FROM milestone_link WHERE id IN (%s)", $ids_list));

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
		$fields = SearchFields_MilestoneLink::getFields();

		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
		$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);

		$select_sql = sprintf("SELECT ".
			"milestone_link.milestone_id as %s, ".
			"milestone_link.source_context as %s, ".
			"milestone_link.source_id as %s, ".
			"milestone_link.container_id as %s ",
			SearchFields_MilestoneLink::MILESTONE_ID,
			SearchFields_MilestoneLink::CONTEXT,
			SearchFields_MilestoneLink::SOURCE_ID,
			SearchFields_MilestoneLink::CONTAINER_ID
		);
			
		$join_sql = "FROM milestone_link ";

		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'milestone_link.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled

		$where_sql = "".
		(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";

		return array(
			'primary_table' => 'milestone_link',
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
		($has_multiple_values ? 'GROUP BY milestone_link.id ' : '').
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
			$object_id = intval($row[SearchFields_MilestoneLink::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
			($has_multiple_values ? "SELECT COUNT(DISTINCT milestone_link.id) " : "SELECT COUNT(milestone_link.id) ").
			$join_sql.
			$where_sql;
			$total = $db->GetOne($count_sql);
		}

		mysql_free_result($rs);

		return array($results,$total);
	}

};

class SearchFields_MilestoneLink implements IDevblocksSearchFields {
	const MILESTONE_ID = 'c_milestone_id';
	const CONTEXT = '_context';
	const SOURCE_ID = 'i_source_id';
	const CONTAINER_ID = 'i_container_id';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::MILESTONE_ID => new DevblocksSearchField(self::MILESTONE_ID, 'milestone_link', 'milestone_id', $translate->_('milestone_link.milestone_id')),
			self::CONTEXT => new DevblocksSearchField(self::CONTEXT, 'milestone_link', 'context', $translate->_('milestone_link.context')),
			self::SOURCE_ID => new DevblocksSearchField(self::SOURCE_ID, 'milestone_link', 'source_id', $translate->_('milestone_link.source_id')),
			self::CONTAINER_ID => new DevblocksSearchField(self::CONTAINER_ID, 'milestone_link', 'container_id', $translate->_('milestone_link.container_id')),
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


class Model_MilestoneLink {
	public $milestone_id;
	public $context;
	public $source_id;
	public $container_id;

	public function getMilestone() {
		return DAO_Milestone::get($this->milestone_id);
	}

}