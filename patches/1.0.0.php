<?php
$db = DevblocksPlatform::getDatabaseService();
$tables = $db->metaTables();

// `issue` ========================
if(!isset($tables['issue'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS issue (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			number INT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			body TEXT NOT NULL,
			created_date INT UNSIGNED NOT NULL DEFAULT 0,
			updated_date INT UNSIGNED NOT NULL DEFAULT 0,
			closed_date INT UNSIGNED NOT NULL DEFAULT 0,
			project_id INT UNSIGNED NOT NULL DEFAULT 0,
			milestone_id INT UNSIGNED NOT NULL DEFAULT 0,
			state VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}

// `milestone` ========================
if(!isset($tables['milestone'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS milestone (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			number INT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT,
			state VARCHAR(255) NOT NULL DEFAULT '',
			created_date INT UNSIGNED NOT NULL DEFAULT 0,
			due_date INT UNSIGNED,
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}

// `project` ========================
if(!isset($tables['project'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS project (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT,
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}

// `issue_link` ========================
if(!isset($tables['issue_link'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS issue_link (
			issue_id INT UNSIGNED NOT NULL DEFAULT 0,
			context VARCHAR(255) NOT NULL DEFAULT '',
			source_id INT UNSIGNED NOT NULL DEFAULT 0,
			container_id INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (issue_id, context, source_id, container_id),
			INDEX issue_id (issue_id),
			INDEX context (context),
			INDEX source_id (source_id),
			INDEX container_id (container_id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}

// `container` ========================
if(!isset($tables['container'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS container (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT,
			enabled INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}


// `container_link` ========================
if(!isset($tables['container_link'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS container_link (
			container_id INT UNSIGNED NOT NULL DEFAULT 0,
			context VARCHAR(255) NOT NULL DEFAULT '',
			source_id INT UNSIGNED NOT NULL DEFAULT 0,
			user_id INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (container_id, context, source_id, user_id),
			INDEX container_id (container_id),
			INDEX context (context),
			INDEX source_id (source_id),
			INDEX user_id (user_id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}

// ===========================================================================
// Enable Cronjobs

if(null != ($cron = DevblocksPlatform::getExtension('cron.issues.wgm', true, true))) {
	$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
	$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '5');
	$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
	$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday 23:00'));
}

if(null != ($cron = DevblocksPlatform::getExtension('cron.sync.containers.wgm', true, true))) {
	$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
	$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '6');
	$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'h');
	$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday 23:00'));
}

if(null != ($cron = DevblocksPlatform::getExtension('cron.recache.containers.wgm', true, true))) {
	$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
	$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '12');
	$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'h');
	$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday 23:00'));
}

if(null != ($cron = DevblocksPlatform::getExtension('cron.milestones.wgm', true, true))) {
	$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
	$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '6');
	$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'h');
	$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday 23:00'));
}