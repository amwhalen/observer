=== Observer ===
Contributors: anukit
Tags: logging, audit, network, multisite, admin
Requires at least: 3.0
Tested up to: 3.6
Stable tag: trunk
License: The MIT License
License URI: http://opensource.org/licenses/MIT

Creates a log file for network/multisite super admin actions.

== Description ==

Creates a log file for network/multisite super admin actions.

The following actions are logged for Super Admin users. WordPress action names are listed in parenthesis:

* change network settings (update_site_option)
* grant super admin privileges  (granted_super_admin)
* revoke super admin privileges (revoked_super_admin)
* upgrade blog (wpmu_upgrade_site)
* add blog (wpmu_new_blog)
* set blog archived (archive_blog/unarchive_blog)
* set blog deleted (make_delete_blog/make_undelete_blog)
* set blog mature (mature_blog/unmature_blog)
* set blog spam (make_spam_blog/make_ham_blog)
* delete blog (delete_blog)
* delete user (wpmu_delete_user)
* delete user (deleted_user)
* change other user's settings (edit_user_profile_update) [NOTE: doesn't show diff]
* change blog setting (updated_option) [NOTE: may occasionally log annoyingly large diffs]

The following actions are logged for ANY user:

* login (wp_login)
* logout (wp_logout)
* new user (user_register)

Some actions that change data will show what the value was before and after.

Below is an example of a log entry when the user 'smith' has changed the Network site name from "WP Local Network" to "WP Local Networks":

`[Tue, 03 Sep 2013 13:45:49 +0000] [smith:1] [update_site_option] [1] [site_name] [(WP Local Network)->(WP Local Networks)] [127.0.0.1]`

Each line in the log file will roughly follow the below example.
Individual actions may log slightly different information, but the DATETIME, USERNAME, USER_ID, ACTION, and IP_ADDRESS sections should always be populated.

`[DATETIME] [USERNAME:USER_ID] [ACTION] [AFFECTED_OBJECT_ID] [DATA_TITLE_OR_KEY] [(VALUE_BEFORE)->(VALUE_AFTER)] [IP_ADDRESS]`

== Installation ==

1. Upload Observer to the `/wp-content/plugins/` directory.
1. Define the 'OBSERVER_LOG' constant in your wp-config.php file. Example: define('OBSERVER_LOG', '/var/log/wordpress_observer.log');
1. Network Activate the plugin through the 'Plugins' menu in the Network Plugins interface.

== Frequently Asked Questions ==

None yet.

== Screenshots ==

None yet.

== Changelog ==

= 1.0.0 =
* Initial release.

