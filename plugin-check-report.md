# Plugin Check Report

**Plugin:** Deploy Forge
**Generated at:** 2026-01-19 00:59:31


## `deploy-forge.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | plugin_updater_detected | Including An Update Checker / Changing Updates functionality. Plugin Updater detected. Use of the Update URI header is not allowed in plugins hosted on WordPress.org. | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#update-checker) |
| 0 | 0 | WARNING | plugin_header_nonexistent_domain_path | The "Domain Path" header in the plugin file must point to an existing folder. Found: "languages" | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#domain-path) |
| 332 | 3 | ERROR | PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound | load_plugin_textdomain() has been discouraged since WordPress version 4.6. When your plugin is hosted on WordPress.org, you no longer need to manually include this function call for translations under your plugin slug. WordPress will automatically load the translations for you as needed. | [Docs](https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/) |

## `includes/class-update-checker.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | plugin_updater_detected | Plugin Updater detected. These are not permitted in WordPress.org hosted plugins. Detected: site_transient_update_plugins |  |
| 0 | 0 | WARNING | update_modification_detected | Plugin Updater detected. Detected code which may be altering WordPress update routines. Detected: auto_update_plugin | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#update-checker) |
| 0 | 0 | WARNING | update_modification_detected | Plugin Updater detected. Detected code which may be altering WordPress update routines. Detected: pre_set_site_transient_update_plugins | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#update-checker) |
| 0 | 0 | WARNING | update_modification_detected | Plugin Updater detected. Detected code which may be altering WordPress update routines. Detected: _site_transient_update_plugins | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#update-checker) |

## `includes/class-deployment-manager.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 981 | 11 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_mkdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: mkdir(). |  |
| 1007 | 4 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |
| 1168 | 13 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_mkdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: mkdir(). |  |
| 1430 | 11 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_mkdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: mkdir(). |  |
| 1445 | 4 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |
| 1504 | 4 | ERROR | WordPress.WP.AlternativeFunctions.unlink_unlink | unlink() is discouraged. Use wp_delete_file() to delete a file. |  |
| 1548 | 11 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_mkdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: mkdir(). |  |
| 1660 | 11 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_mkdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: mkdir(). |  |
| 2133 | 11 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_mkdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: mkdir(). |  |
| 2157 | 13 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_mkdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: mkdir(). |  |
| 2165 | 5 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_chmod | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: chmod(). |  |
| 2199 | 5 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_rmdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: rmdir(). |  |
| 2201 | 5 | ERROR | WordPress.WP.AlternativeFunctions.unlink_unlink | unlink() is discouraged. Use wp_delete_file() to delete a file. |  |
| 2205 | 10 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_rmdir | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: rmdir(). |  |

## `README.md`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | missing_readme_header_tested | The "Tested up to" header is missing in the readme file. | [Docs](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/#readme-header-information) |
| 0 | 0 | ERROR | no_license | Missing "License". Please update your readme with a valid GPLv2 (or later) compatible license. | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#no-gpl-compatible-license-declared) |
| 0 | 0 | ERROR | no_stable_tag | Invalid or missing Stable Tag. Your Stable Tag is meant to be the stable version of your plugin and it needs to be exactly the same with the Version in your main plugin file's header. Any mismatch can prevent users from downloading the correct plugin files from WordPress.org. | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#incorrect-stable-tag) |
| 0 | 0 | WARNING | readme_parser_warnings_trimmed_short_description | The "Short Description" section is too long and was truncated. A maximum of 150 characters is supported. |  |

## `admin/class-admin-pages.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 297 | 3 | ERROR | PluginCheck.CodeAnalysis.SettingSanitization.register_settingMissing | Sanitization missing for register_setting(). | [Docs](https://developer.wordpress.org/reference/functions/register_setting/) |

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
| 1376 | 4 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |
| 1426 | 5 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |

## `includes/class-settings.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 384 | 5 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |

## `includes/class-debug-logger.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 99 | 3 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |

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

## `templates/dashboard-page.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 177 | 60 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: &quot;$deployment&quot;. |  |

## `templates/history-page.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 37 | 49 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: &quot;$deployment&quot;. |  |
