# Plugin Check Report

**Plugin:** Deploy Forge
**Generated at:** 2026-01-19 05:20:57


## `readme.txt`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | outdated_tested_upto_header | Tested up to: 6.7 < 6.9. The "Tested up to" value in your plugin is not set to the current version of WordPress. This means your plugin will not show up in searches, as we require plugins to be compatible and documented as tested up to the most recent version of WordPress. | [Docs](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/#readme-header-information) |

## `includes/class-webhook-handler.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 974 | 5 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |
| 1007 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1007 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1007 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_row($wpdb-&gt;prepare(\n\t\t\t\t&quot;SELECT * FROM {$table_name} WHERE remote_deployment_id = %s ORDER BY id DESC LIMIT 1&quot;,\n\t\t\t\t$remote_id\n\t\t\t))\n$table_name assigned unsafely at line 1005:\n $table_name = $wpdb-&gt;prefix . &#039;github_deployments&#039;\n$remote_id used without escaping. |  |
| 1009 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table_name} at &quot;SELECT * FROM {$table_name} WHERE remote_deployment_id = %s ORDER BY id DESC LIMIT 1&quot; |  |
| 1031 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1031 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1031 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_row($wpdb-&gt;prepare(\n\t\t\t\t&quot;SELECT * FROM {$table_name} WHERE commit_hash = %s AND status IN (&#039;pending&#039;, &#039;building&#039;) ORDER BY id DESC LIMIT 1&quot;,\n\t\t\t\t$commit_sha\n\t\t\t))\n$table_name assigned unsafely at line 1029:\n $table_name = $wpdb-&gt;prefix . &#039;github_deployments&#039;\n$commit_sha used without escaping. |  |
| 1033 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table_name} at &quot;SELECT * FROM {$table_name} WHERE commit_hash = %s AND status IN (&#039;pending&#039;, &#039;building&#039;) ORDER BY id DESC LIMIT 1&quot; |  |
| 1195 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1195 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1195 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_row($wpdb-&gt;prepare( &quot;SELECT * FROM {$table_name} WHERE workflow_run_id = %d ORDER BY id DESC LIMIT 1&quot;, $run_id ))\n$table_name assigned unsafely at line 1192:\n $table_name = $wpdb-&gt;prefix . &#039;github_deployments&#039;\n$deployment assigned unsafely at line 1195:\n $deployment = $wpdb-&gt;get_row(\n\t\t\t$wpdb-&gt;prepare( &quot;SELECT * FROM {$table_name} WHERE workflow_run_id = %d ORDER BY id DESC LIMIT 1&quot;, $run_id )\n\t\t)\n$run_id assigned unsafely at line 1146:\n $run_id = $workflow_run[&#039;id&#039;] ?? 0\n$workflow_run[&#039;id&#039;] used without escaping. |  |
| 1196 | 20 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table_name} at &quot;SELECT * FROM {$table_name} WHERE workflow_run_id = %d ORDER BY id DESC LIMIT 1&quot; |  |
| 1202 | 25 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_row($wpdb-&gt;prepare(\n\t\t\t\t\t&quot;SELECT * FROM {$table_name} WHERE commit_hash = %s AND status IN (&#039;pending&#039;, &#039;building&#039;) ORDER BY id DESC LIMIT 1&quot;,\n\t\t\t\t\t$head_sha\n\t\t\t\t))\n$table_name assigned unsafely at line 1192:\n $table_name = $wpdb-&gt;prefix . &#039;github_deployments&#039;\n$deployment assigned unsafely at line 1202:\n $deployment = $wpdb-&gt;get_row(\n\t\t\t\t$wpdb-&gt;prepare(\n\t\t\t\t\t&quot;SELECT * FROM {$table_name} WHERE commit_hash = %s AND status IN (&#039;pending&#039;, &#039;building&#039;) ORDER BY id DESC LIMIT 1&quot;,\n\t\t\t\t\t$head_sha\n\t\t\t\t)\n\t\t\t)\n$run_id assigned unsafely at line 1146:\n $run_id = $workflow_run[&#039;id&#039;] ?? 0\n$head_sha assigned unsafely at line 1149:\n $head_sha = $workflow_run[&#039;head_sha&#039;] ?? &#039;&#039;\n$workflow_run[&#039;id&#039;] used without escaping.\n$workflow_run[&#039;head_sha&#039;] used without escaping. |  |
| 1202 | 27 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1202 | 27 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1204 | 6 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table_name} at &quot;SELECT * FROM {$table_name} WHERE commit_hash = %s AND status IN (&#039;pending&#039;, &#039;building&#039;) ORDER BY id DESC LIMIT 1&quot; |  |
| 1427 | 5 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |

## `includes/class-deployment-manager.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 1037 | 4 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |
| 1475 | 4 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |

## `includes/class-database.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 149 | 34 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 149 | 34 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 151 | 7 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SHOW COLUMNS FROM {$this-&gt;table_name} LIKE %s&quot; |  |
| 158 | 13 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $column used in $wpdb-&gt;query(&quot;ALTER TABLE {$this-&gt;table_name} ADD COLUMN {$column} {$type}&quot;)\n$column assigned unsafely at line 148:\n $column =&gt; \n$type used without escaping.\n$column_exists assigned unsafely at line 149:\n $column_exists = $wpdb-&gt;get_results(\n\t\t\t\t\t$wpdb-&gt;prepare(\n\t\t\t\t\t\t&quot;SHOW COLUMNS FROM {$this-&gt;table_name} LIKE %s&quot;,\n\t\t\t\t\t\t$column\n\t\t\t\t\t)\n\t\t\t\t) |  |
| 158 | 35 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |
| 163 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 163 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 164 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SHOW INDEX FROM {$this-&gt;table_name} WHERE Key_name = &#039;remote_deployment_id&#039;&quot; |  |
| 169 | 31 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |
| 200 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 247 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 247 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 271 | 20 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} WHERE id = %d&quot; |  |
| 291 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} WHERE commit_hash = %s ORDER BY id DESC LIMIT 1&quot; |  |
| 314 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} WHERE status = %s ORDER BY created_at DESC LIMIT %d&quot; |  |
| 336 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d&quot; |  |
| 355 | 4 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} WHERE status = &#039;success&#039; ORDER BY deployed_at DESC LIMIT 1&quot; |  |
| 373 | 4 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} WHERE status IN (&#039;pending&#039;, &#039;building&#039;, &#039;queued&#039;) ORDER BY created_at ASC&quot; |  |
| 389 | 4 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} WHERE status = &#039;queued&#039; ORDER BY created_at ASC&quot; |  |
| 445 | 4 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name} WHERE status IN (&#039;pending&#039;, &#039;building&#039;) ORDER BY created_at DESC LIMIT 1&quot; |  |
| 478 | 33 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |
| 497 | 21 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT COUNT(*) FROM {$this-&gt;table_name} WHERE status = %s&quot; |  |
| 522 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;SELECT * FROM {$this-&gt;table_name}\n |  |
| 551 | 20 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_name} at &quot;DELETE FROM {$this-&gt;table_name} WHERE created_at &lt; %s&quot; |  |
