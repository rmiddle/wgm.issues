<?php

if (class_exists('Extension_ActivityTab')):
class IssuesActivityTab extends Extension_ActivityTab {
	const VIEW_ACTIVITY_ISSUES = 'activity_issue';

	function showTab() {
		$translate = DevblocksPlatform::getTranslationService();
		$tpl = DevblocksPlatform::getTemplateService();

		$defaults = new C4_AbstractViewModel();
		$defaults->class_name = 'View_Issue';
		$defaults->id = self::VIEW_ACTIVITY_ISSUES;
		$defaults->name = $translate->_('issues.activity.tab');
		$defaults->view_columns = array(
			SearchFields_Issue::CREATED_DATE,
			SearchFields_Issue::UPDATED_DATE
		);
		$defaults->renderSortBy = SearchFields_Issue::CREATED_DATE;
		$defaults->renderSortAsc = 0;

		$view = C4_AbstractViewLoader::getView(self::VIEW_ACTIVITY_ISSUES, $defaults);

		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:wgm.issues::activity_tab/index.tpl');
	}
}
endif;

class Wgm_Controller extends DevblocksControllerExtension {
	
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}

	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
		$worker = CerberusApplication::getActiveWorker();
		if(empty($worker)) return;
		
		$stack = $request->path;
		array_shift($stack); // internal
		
		@$action = array_shift($stack) . 'Action';
		
		switch($action) {
			case NULL:
				// [TODO] Index/page render
				break;
				 
			default:
				// Default action, call arg as a method suffixed with Action
				if(method_exists($this,$action)) {
					call_user_func(array(&$this, $action));
				}
			break;
		}
	}

	function writeResponse(DevblocksHttpResponse $response) {
		return;
	}
	
	public function showIssuePeekAction() {
		
	}
	
	public function displayIssueAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'int', 0);
		
	}
	
}

if(class_exists('Extension_PageMenuItem')):
class Wgm_SetupPluginsMenuItem extends Extension_PageMenuItem {
	const POINT = 'wgmissue.setup.menu.plugins.issue';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('extension', $this);
		$tpl->display('devblocks:wgm.issues::setup/menu_item.tpl');
	}
};
endif;

if(class_exists('Extension_PageSection')):
class Wgm_SetupSection extends Extension_PageSection {
	const ID = 'wgmissue.setup.issue';
	
	function render() {
		// check whether extensions are loaded or not
		$extensions = array(
			'oauth' => extension_loaded('oauth')
		);
		$tpl = DevblocksPlatform::getTemplateService();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'issue');
		
		$params = array(
			'client_id' => DevblocksPlatform::getPluginSetting('wgm.issues', 'client_id', ''),
			'client_secret' => DevblocksPlatform::getPluginSetting('wgm.issues', 'client_secret', ''),
			'repos' => DAO_Repository::getAll(),
			'users' => DAO_User::getWhere(),
		);
		
		$tpl->assign('params', $params);
		$tpl->assign('extensions', $extensions);
		
		$tpl->display('devblocks:wgm.issues::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			@$client_id = DevblocksPlatform::importGPC($_REQUEST['client_id'], 'string', '');
			@$client_secret = DevblocksPlatform::importGPC($_REQUEST['client_secret'], 'string', '');
			
			if(empty($client_id) || empty($client_secret))
				throw new Exception("Both the API Auth Token and URL are required.");
			
			DevblocksPlatform::setPluginSetting('wgm.issues', 'client_id', $client_id);
			DevblocksPlatform::setPluginSetting('wgm.issues', 'client_secret', $client_secret);
			
		    echo json_encode(array('status' => true, 'message' => 'Saved!'));
		    return;
			
		} catch (Exception $e) {
			echo json_encode(array('status' => false, 'error' => $e->getMessage()));
			return;
		}
	}
	
	function toggleRepoAction() {
		@$repo_id = DevblocksPlatform::importGPC($_REQUEST['repo_id'], 'int', 0);
		@$repo_action = DevblocksPlatform::importGPC($_REQUEST['repo_action'], 'string', '');
		
		$repos = DAO_Repository::getAll();
		
		try {
			if(!array_key_exists($repo_id, $repos))
				throw new Exception("This is not the repo you are looking for. Does it exist?");
			$user = DAO_User::get($repos[$repo_id]->user_id);
			$repository = sprintf("%s/%s",$user->login, $repos[$repo_id]->name);
			// enable/disable repo
			if($repo_action == 'disable') {
				$fields = array(
					DAO_Repository::ENABLED => false
				);
				echo json_encode(array('status'=>true,'message'=>$repository.' was disabled!'));
			} elseif ($repo_action == 'enable') {
				$fields = array(
					DAO_Repository::ENABLED => false
				);
				echo json_encode(array('status'=>true,'message'=>$repository.' was enabled!'));
			}
			
			DAO_Repository::update($repo_id, $fields);
				
		} catch (Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
	
};
endif;

abstract class Extension_IssueSource extends DevblocksExtension {
	const POINT = 'source.issues.wgm';
	
	public function sync($max_issues, &$synced) {}
}

abstract class Extension_ContainerSource extends DevblocksExtension {
	const POINT = 'source.containers.wgm';

	public function sync($max_containers, &$synced) {
	}
}

abstract class Extension_MilestoneSource extends DevblocksExtension {
	const POINT = 'source.milestones.wgm';

	public function sync($max_milestones, &$synced) {
	}
}


class IssueCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();
	
		$logger->info("[Issues] Syncing Issues");
	
		
		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);
		
		// Max issues to sync
		$max_issues = $this->getParam('max_issues', 100);
		
		// Load source extensions
		$source_exts = DevblocksPlatform::getExtensions(Extension_IssueSource::POINT, true);
		
		$synced = 0;
		foreach($source_exts as $source) {
			$logger->info(sprintf('[Issues] Syncing issues from %s', $source->manifest->name));
			$source->sync($max_issues, $synced);
			// check amount of issues synced
			if($synced == $max_issues) {
				break;
			}
		}
		
		$logger->info("[Issues] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}
	
	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();
	
		$tpl->assign('max_issues', $this->getParam('max_issues', 100));
	
		$tpl->display('devblocks:wgm.issues::setup/cron/issues.tpl');
	}
	
	function saveConfigurationAction() {
		@$max_issues = DevblocksPlatform::importGPC($_POST['max_issues'],'integer');
		$this->setParam('max_issues', $max_issues);
	}
}

class ContainerSyncCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();

		$logger->info("[Issues] Syncing Containers");

		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);

		// Max containers to sync
		$max_containers = $this->getParam('max_containers', 100);
		
		// Load container sources
		$source_exts = DevblocksPlatform::getExtensions(Extension_ContainerSource::POINT, true);
		
		$synced = 0;
		foreach($source_exts as $source) {
			$logger->info(sprintf('[Issues] Syncing containers from %s', $source->manifest->name));
			$source->sync($max_containers, $synced);
			// check amount of issues synced
			if($synced == $max_containers) {
				break;
			}
		}

		$logger->info("[Issues] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('max_containers', $this->getParam('max_containers', 100));

		$tpl->display('devblocks:wgm.issues::setup/cron/containers.tpl');
	}

	function saveConfigurationAction() {
		@$max_containers = DevblocksPlatform::importGPC($_POST['max_containers'],'integer');
		$this->setParam('max_containers', $max_containers);
	}
}

class ContainerRecacheCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();

		$logger->info("[Issues] Recaching Containers");

		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);

		// Max containers to sync
		$max_containers = $this->getParam('max_containers', 100);

		// Load container sources
		$source_exts = DevblocksPlatform::getExtensions(Extension_ContainerSource::POINT, true);

		$synced = 0;
		foreach($source_exts as $source) {
			$logger->info(sprintf('[Issues] Recaching containers from %s', $source->manifest->name));
			$source->recache($max_containers, $synced);
			// check amount of issues synced
			if($synced == $max_containers) {
				break;
			}
		}

		$logger->info("[Issues] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('max_containers', $this->getParam('max_containers', 100));

		$tpl->display('devblocks:wgm.issues::setup/cron/containers.tpl');
	}

	function saveConfigurationAction() {
		@$max_containers = DevblocksPlatform::importGPC($_POST['max_containers'],'integer');
		$this->setParam('max_containers', $max_containers);
	}
}

class MilestoneCron extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();

		$logger->info("[Issues] Syncing Milestones");

		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);

		// Max milestones to sync
		$max_milestones = $this->getParam('max_milestones', 100);

		// Load milestone sources
		$source_exts = DevblocksPlatform::getExtensions(Extension_MilestoneSource::POINT, true);

		$synced = 0;
		foreach($source_exts as $source) {
			$logger->info(sprintf('[Issues] Syncing milestones from %s', $source->manifest->name));
			$source->sync($max_milestones, $synced);
			// check amount of issues synced
			if($synced == $max_milestones) {
				break;
			}
		}

		$logger->info("[Issues] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('max_milestones', $this->getParam('max_milestones', 100));

		$tpl->display('devblocks:wgm.issues::setup/cron/milestones.tpl');
	}

	function saveConfigurationAction() {
		@$max_milestones = DevblocksPlatform::importGPC($_POST['max_milestones'],'integer');
		$this->setParam('max_milestones', $max_milestones);
	}
}

class Wgm_SyncMilestones extends CerberusCronPageExtension {
	function run() {
		$logger = DevblocksPlatform::getConsoleLog();

		$logger->info("[] Syncing  Repositories");

		if (!extension_loaded("oauth")) {
			$logger->err("[] The 'oauth' extension is not loaded.  Aborting!");
			return false;
		}

		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);

		$issue = Wgm_API::getInstance();

		// get config
		$token = DevblocksPlatform::getPluginSetting('wgm.issues', 'access_token', '');
		$issue->setCredentials($token);

		// get last sync repo
		$last_sync_repo = $this->getParam('repos.last_repo', '');

		// max repos to sync
		$max_repos = $this->getParam('max_repos', 100);

		// get repos
		$repos = $issue->get('user/repos');

		$synced = 0;

		if($last_sync_repo !== '' && array_key_exists($last_sync_repo, $repos))
			$logger->info(sprintf("[] Starting sync from %s/%s", $repos[$last_sync_repo]['user'], $repos[$last_sync_repo]['name']));
		
		foreach($repos as $repo) {
			if($last_sync_repo !== '' && $repo['id'] != $last_sync_repo) {
				$logger->info(sprintf("[] Skipping repository %s!", $repository));
				continue;
			}
			// does the owner of the repository exist in the DB?
			if(null === $user = DAO_User::getByLogin($repo['owner']['login'])) {
				$user = $issue->get(sprintf('users/%s', $repo['owner']['login']));

				$fields = array(
					DAO_User::NUMBER => $user['id'],
					DAO_User::LOGIN => $user['login'],
					DAO_User::NAME => $user['name'],
					DAO_User::EMAIL => $user['email']
				);
				$user = DAO_User::create($fields);
			}
			$fields = array(
				DAO_Repository::NUMBER => $repo['id'],
				DAO_Repository::NAME => $repo['name'],
				DAO_Repository::DESCRIPTION => $repo['description'],
				DAO_Repository::USER_ID => $user->id,
				DAO_Repository::ENABLED => true
			);
				
			// does the repo exist in the DB?
			if(null === $repository = DAO_Repository::getByNumber($repo['id'])) {
				DAO_Repository::create($fields);
			} else {
				DAO_Repository::update($repository->id, $fields);
			}
			$synced++;
			// check amount of repos synced
			if($synced == $max_repos) {
				$this->setParam('repos.last_repo', $repo_id);
				break 2;
					
			}
		}
		foreach($repos as $repo) {
			// is the repo enabled?
			$user = DAO_User::get($repo->user_id);
			$repository = sprintf("%s/%s", $user->login, $repo->name);
			if($last_sync_repo !== '' && $repo_id != $last_sync_repo) {
				$logger->info(sprintf("[] Skipping repository %s!", $repository));
				continue;
			} elseif(!$repo->enabled) {
				$logger->info(sprintf("[] Skipping repository %s since it isn't enabled!", $repository));
				continue;
			}

		}

		$logger->info("[] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}

	function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('max_milestones', $this->getParam('max_milestones', 100));

		$tpl->display('devblocks:wgm.issues::setup/cron/milestones.tpl');
	}

	function saveConfigurationAction() {
		@$max_milestones = DevblocksPlatform::importGPC($_POST['max_milestones'],'integer');
		$this->setParam('max_milestones', $max_milestones);
	}
}