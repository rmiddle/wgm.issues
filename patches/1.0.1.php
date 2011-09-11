<?php
$db = DevblocksPlatform::getDatabaseService();
$tables = $db->metaTables();

list($columns, $indexes) = $db->metaTable('milestone');

if(isset($columns['number']))
	$db->Execute("ALTER TABLE milestone DROP COLUMN number");

// `milestone_link` ========================
if(!isset($tables['milestone_link'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS milestone_link (
			milestone_id INT UNSIGNED NOT NULL DEFAULT 0,
			context VARCHAR(255) NOT NULL DEFAULT '',
			source_id INT UNSIGNED NOT NULL DEFAULT 0,
			container_id INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (milestone_id, context, source_id, container_id),
			INDEX milestone_id (milestone_id),
			INDEX context (context),
			INDEX source_id (source_id),
			INDEX container_id (container_id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}