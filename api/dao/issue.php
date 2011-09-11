<?php
class DAO_Issue extends C4_ORMHelper {
	const ID = 'id';
	const TITLE = 'title';
	const BODY = 'body';
	const CREATED_DATE = 'created_date';
	const UPDATED_DATE = 'updated_date';
	const CLOSED_DATE = 'closed_date';
	const MILESTONE_ID = 'milestone_id';
	const PROJECT_ID = 'project_id';
	const STATE = 'state';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "INSERT INTO issue () VALUES ()";
		$db->Execute($sql);
		$id = $db->LastInsertId();

		self::update($id, $fields);

		return $id;
	}

	static function update($ids, $fields) {
		parent::_update($ids, 'issue', $fields);
	}

	static function updateWhere($fields, $where) {
		parent::_updateWhere('issue', $fields, $where);
	}
	

	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Issue[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);

		// SQL
		$sql = "SELECT id, title, body, created_date, updated_date, closed_date, project_id, milestone_id, state ".
			"FROM issue ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);

		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_Issue
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
	* @return Model_Issue[]
	*/
	static function getByNumber($context, $source_id, $container_id) {
		if(null !== $issue_link = DAO_IssueLink::getByNumber($context, $source_id, $container_id)) {
			return $issue_link->getIssue();
		}
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Issue[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();

		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_Issue();
			$object->id = $row['id'];
			$object->title = $row['title'];
			$object->body = $row['body'];
			$object->created_date = $row['created_date'];
			$object->updated_date = $row['updated_date'];
			$object->closed_date = $row['closed_date'];
			$object->project_id = $row['project_id'];
			$object->milestone_id = $row['milestone_id'];
			$object->state = $row['state'];
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

		$db->Execute(sprintf("DELETE FROM issue WHERE id IN (%s)", $ids_list));

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
		$fields = SearchFields_Issue::getFields();

		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
		$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);

		$select_sql = sprintf("SELECT ".
			"g.id as %s, ".
			"g.title as %s, ".
			"g.body as %s, ".
			"g.created_date as %s, ".
			"g.updated_date as %s, ".
			"g.closed_date as %s, ".
			"g.project_id as %s, ".
			"g.milestone_id as %s, ".
			"g.state as %s, ".
			"gp.name as %s, ".
			"gm.name as %s ",
			SearchFields_Issue::ID,
			SearchFields_Issue::TITLE,
			SearchFields_Issue::BODY,
			SearchFields_Issue::CREATED_DATE,
			SearchFields_Issue::UPDATED_DATE,
			SearchFields_Issue::CLOSED_DATE,
			SearchFields_Issue::PROJECT_ID,
			SearchFields_Issue::MILESTONE_ID,
			SearchFields_Issue::STATE,
			SearchFields_Issue::PROJECT_NAME,
			SearchFields_Issue::MILESTONE_NAME
		);
			
		$join_sql = "FROM issue g ".
			"LEFT JOIN project gp ON (g.project_id=gp.id) ".
			"LEFT JOIN milestone gm ON (g.milestone_id=gm.id) "
			;
		
		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'issue.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled

		$where_sql = "".
		(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
		
		return array(
			'primary_table' => 'issue',
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
		($has_multiple_values ? 'GROUP BY issue.id ' : '').
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
			$object_id = intval($row[SearchFields_Issue::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
			($has_multiple_values ? "SELECT COUNT(DISTINCT g.id) " : "SELECT COUNT(g.id) ").
			$join_sql.
			$where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		mysql_free_result($rs);

		return array($results,$total);
	}

};

class SearchFields_Issue implements IDevblocksSearchFields {
	const ID = 'g_id';
	const TITLE = 'g_title';
	const BODY = 'g_body';
	const CREATED_DATE = 'g_created_date';
	const UPDATED_DATE = 'g_updated_date';
	const CLOSED_DATE = 'g_closed_date';
	const PROJECT_ID = 'g_project_id';
	const MILESTONE_ID = 'g_milestone_id';
	const STATE = 'g_state';
	
	const PROJECT_NAME = 'gp_name';
	const MILESTONE_NAME = 'gm_name';
	const NUMBER = 'il_number';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'issue', 'id', $translate->_('issue.id')),
			self::TITLE => new DevblocksSearchField(self::TITLE, 'issue', 'title', $translate->_('issue.title')),
			self::BODY => new DevblocksSearchField(self::BODY, 'issue', 'body', $translate->_('issue.body')),
			self::CREATED_DATE => new DevblocksSearchField(self::CREATED_DATE, 'issue', 'created_date', $translate->_('issue.created_date')),
			self::UPDATED_DATE => new DevblocksSearchField(self::UPDATED_DATE, 'issue', 'updated_date', $translate->_('issue.updated_date')),
			self::CLOSED_DATE => new DevblocksSearchField(self::CLOSED_DATE, 'issue', 'closed_date', $translate->_('issue.closed_date')),
			
			self::STATE => new DevblocksSearchField(self::STATE, 'issue', 'state', $translate->_('issue.state')),

			self::NUMBER => new DevblocksSearchField(self::NUMBER, 'il', 'number', $translate->_('issue.number')),
			self::PROJECT_NAME => new DevblocksSearchField(self::PROJECT_NAME, 'gp', 'name', $translate->_('issue.project_id')),
			self::MILESTONE_NAME => new DevblocksSearchField(self::MILESTONE_NAME, 'gm', 'name', $translate->_('issue.milestone_id')),
			
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

class Model_Issue {
	public $id;
	public $title;
	public $body;
	public $created_date;
	public $updated_date;
	public $closed_date;
	public $project_id;
	public $milestone_id;
	public $state;
};

class View_Issue extends C4_AbstractView {
	const DEFAULT_ID = 'issue';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();

		$this->id = self::DEFAULT_ID;
		// [TODO] Name the worklist view
		$this->name = $translate->_('common.serarch_results');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Issue::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Issue::ID,
			SearchFields_Issue::NUMBER,
			SearchFields_Issue::TITLE,
			SearchFields_Issue::BODY,
			SearchFields_Issue::CREATED_DATE,
			SearchFields_Issue::UPDATED_DATE,
			SearchFields_Issue::CLOSED_DATE,
			SearchFields_Issue::PROJECT_NAME,
			SearchFields_Issue::MILESTONE_NAME,
			SearchFields_Issue::STATE,
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
		$objects = DAO_Issue::search(
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
		return $this->_doGetDataSample('DAO_Issue', $size);
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
		$tpl->display('devblocks:wgm.issues::issues/view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		// [TODO] Move the fields into the proper data type
		switch($field) {
			case SearchFields_Issue::ID:
			case SearchFields_Issue::NUMBER:
			case SearchFields_Issue::TITLE:
			case SearchFields_Issue::BODY:
			case SearchFields_Issue::CREATED_DATE:
			case SearchFields_Issue::UPDATED_DATE:
			case SearchFields_Issue::CLOSED_DATE:
			case SearchFields_Issue::PROJECT_NAME:
			case SearchFields_Issue::MILESTONE_NAME:
			case SearchFields_Issue::STATE:
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
		return SearchFields_Issue::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		// [TODO] Move fields into the right data type
		switch($field) {
			case SearchFields_Issue::ID:
			case SearchFields_Issue::NUMBER:
			case SearchFields_Issue::TITLE:
			case SearchFields_Issue::BODY:
			case SearchFields_Issue::CREATED_DATE:
			case SearchFields_Issue::UPDATED_DATE:
			case SearchFields_Issue::CLOSED_DATE:
			case SearchFields_Issue::MILESTONE_ID:
			case SearchFields_Issue::STATE:
			case SearchFields_Issue::PROJECT_NAME:
			case SearchFields_Issue::MILESTONE_NAME:
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
					//$change_fields[DAO_Issue::EXAMPLE] = 'some value';
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
			list($objects,$null) = DAO_Issue::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Issue::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));

		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
		$batch_ids = array_slice($ids,$x,100);
			
		DAO_Issue::update($batch_ids, $change_fields);

		// Custom Fields
		//self::_doBulkSetCustomFields(ChCustomFieldSource_Issue::ID, $custom_fields, $batch_ids);
			
		unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Issue extends Extension_DevblocksContext {
	function authorize($context_id, Model_Worker $worker) {
		return TRUE;
	}

	function getMeta($context_id) {
		$issue = DAO_Issue::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();

		$friendly = DevblocksPlatform::strToPermalink($issue->title);

		return array(
			'id' => $issue->id,
			'name' => $issue->title,
			'permalink' => $url_writer->writeNoProxy(sprintf("c=issues&action=issues&id=%d-%s", $issue->id, $friendly, true)),
		);
	}

	function getContext($issue, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
		$prefix = 'Milestone:';

		$translate = DevblocksPlatform::getTranslationService();
		
		// Polymorph
		if(is_numeric($issue)) {
			$issue = DAO_Issue::get($issue);
		} elseif($issue instanceof Model_Issue) {
			// It's what we want already.
		} else {
			$article = null;
		}
		/* @var $article Model_Issue */
			
		// Token labels
		$token_labels = array(
			'id' => $prefix.$translate->_('common.id'),
			'title' => $prefix.$translate->_('issue.title'),
			'body' => $prefix.$translate->_('issue.body'),
			'created|date' => $prefix.$translate->_('issue.created_date'),
			'updated|date' => $prefix.$translate->_('issue.updated_date'),
			'closed|date' => $prefix.$translate->_('issue.closed_date'),
			'milestone_id'=> $prefix.$translate->_('issue.milestone_id'),
			'state' => $prefix.$translate->_('issue.state'),
		);

		// Token values
		$token_values = array();

		// Token values
		if(null != $article) {
			$token_values['id'] = $issue->id;
			$token_values['title'] = $issue->title;
			$token_values['body'] = $issue->body;
			$token_values['created_date'] = $issue->created_date;
			$token_values['updated_date'] = $issue->updated_date;
			$token_values['closed_date'] = $issue->closed_date;
			$token_values['milestone_id'] = $issue->milestone_id;
			$token_values['state'] = $issue->state;
				
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
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=issues&action=issues&id=%d-%s", $issue->id, DevblocksPlatform::strToPermalink($issue->title)), true);
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
			SearchFields_Issue::STATE => new DevblocksSearchCriteria(SearchFields_Issue::STATE,'!=','closed'),
		), true);
		$view->renderSortBy = SearchFields_Issue::UPDATED_DATE;
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
			new DevblocksSearchCriteria(SearchFields_Issue::CONTEXT_LINK,'=',$context),
			new DevblocksSearchCriteria(SearchFields_Issue::CONTEXT_LINK_ID,'=',$context_id),
			);
		}

		$view->addParamsRequired($params_req, true);

		$view->renderTemplate = 'context';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
};

class DAO_IssueLink extends C4_ORMHelper {
	const ISSUE_ID = 'issue_id';
	const CONTEXT = 'context';
	const SOURCE_ID = 'source_id';
	const CONTAINER_ID = 'container_id';

	static function create($issue_id, $context, $source_id, $container_id) {
		$db = DevblocksPlatform::getDatabaseService();
		$db->Execute(sprintf("INSERT IGNORE INTO issue_link (issue_id, context, source_id, container_id) ".
			"VALUES (%d, %s, %d, %d)",
			$issue_id,
			$db->qstr($context),
			$source_id,
			$container_id
		));
	}
	
	/**
	 * @param string $where
	 * @return Model_IssueLink[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "SELECT issue_id, context, source_id, container_id ".
			"FROM issue_link ".
		(!empty($where) ? sprintf("WHERE %s ",$where) : "");
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}
	/**
	* @param string $context
	* @param int $source_id
	* @param int $container_id
	* @return Model_IssueLink[]
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
	 * @param resource $rs
	 * @return Model_IssueLink[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_IssueLink();
			$object->issue_id = $row['issue_id'];
			$object->context = $row['context'];
			$object->source_id = $row['source_id'];
			$object->container_id = $row['container_id'];
			$objects[$object->issue_id] = $object;
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
		
		$db->Execute(sprintf("DELETE FROM issue_link WHERE id IN (%s)", $ids_list));
		
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
		$fields = SearchFields_IssueLink::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

        list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"issue_link.issue_id as %s, ".
			"issue_link.source_context as %s, ".
			"issue_link.source_id as %s, ".
			"issue_link.container_id as %s ",
			SearchFields_IssueLink::ISSUE_ID,
			SearchFields_IssueLink::CONTEXT,
			SearchFields_IssueLink::SOURCE_ID,
			SearchFields_IssueLink::CONTAINER_ID
		);
			
		$join_sql = "FROM issue_link ";
		
		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'issue_link.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
	
		return array(
			'primary_table' => 'issue_link',
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
			($has_multiple_values ? 'GROUP BY issue_link.id ' : '').
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
			$object_id = intval($row[SearchFields_IssueLink::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql = 
				($has_multiple_values ? "SELECT COUNT(DISTINCT issue_link.id) " : "SELECT COUNT(issue_link.id) ").
				$join_sql.
				$where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		mysql_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_IssueLink implements IDevblocksSearchFields {
	const ISSUE_ID = 'i_issue_id';
	const CONTEXT = 'i_context';
	const SOURCE_ID = 'i_source_id';
	const CONTAINER_ID = 'i_container_id';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::ISSUE_ID => new DevblocksSearchField(self::ISSUE_ID, 'issue_link', 'issue_id', $translate->_('issue_link.issue_id')),
			self::CONTEXT => new DevblocksSearchField(self::CONTEXT, 'issue_link', 'context', $translate->_('issue_link.context')),
			self::SOURCE_ID => new DevblocksSearchField(self::SOURCE_ID, 'issue_link', 'source_id', $translate->_('issue_link.source_id')),
			self::CONTAINER_ID => new DevblocksSearchField(self::CONTAINER_ID, 'issue_link', 'container_id', $translate->_('issue_link.container_id')),
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


class Model_IssueLink {
	public $issue_id;
	public $context;
	public $source_id;
	public $container_id;
	
	public function getIssue() {
		return DAO_Issue::get($this->issue_id);
	}
}