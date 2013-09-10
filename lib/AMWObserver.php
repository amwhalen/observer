<?php

class AMWObserver {

	protected $logfile;
	protected $version = '1.0.1';

	/**
	 * Constructor
	 */
	public function __construct($logfile) {
		$this->setLogfile($logfile);
	}

	/**
	 * Start keeping track of what super admins are doing
	 * @return void
	 */
	public function observe() {

		// add a page to the network admin settings
		if (function_exists('is_network_admin')) {
			add_action('network_admin_menu', array(&$this, 'addOptionsPage'));
		}

		// actions that are linked to a logged-in super admin
		if (is_super_admin()) {
			$this->addAction('update_site_option',       3);
			$this->addAction('updated_option',           3);
			$this->addAction('granted_super_admin',      1);
			$this->addAction('revoked_super_admin',      1);
			$this->addAction('wpmu_upgrade_site',        1);
			$this->addAction('wpmu_new_blog',            6);
			$this->addAction('delete_blog',              2);
			$this->addAction('make_delete_blog',         1);
			$this->addAction('make_undelete_blog',       1);
			$this->addAction('archive_blog',             1);
			$this->addAction('unarchive_blog',           1);
			$this->addAction('mature_blog',              1);
			$this->addAction('unmature_blog',            1);
			$this->addAction('make_spam_blog',           1);
			$this->addAction('make_ham_blog',            1);
			$this->addAction('wpmu_delete_user',         1);
			$this->addAction('deleted_user',             1);
			$this->addAction('edit_user_profile_update', 1);
		}

		// actions we want to track even when there's no logged-in user
		$this->addAction('wp_login',      2);
		$this->addAction('wp_logout',     1);
		$this->addAction('user_register', 1);

	}

	/**
	 * Adds a new action to watch.
	 * @param string  $action         The action hook name.
	 * @param integer $numberOfParams The number of paramsters that the action passes to the callback function.
	 */
	protected function addAction($action, $numberOfParams=1) {
		$priority = 10;
		add_action($action, array(&$this, 'log_'.$action), $priority, $numberOfParams);
	}

	/**
	 * Logs wp_login
	 * @param  string $user_login The username.
	 * @param  Object $user       The user object.
	 * @return void
	 */
	public function log_wp_login($user_login, $user) {
		if (is_super_admin($user->ID)) {
			$this->log('wp_login', $user->ID, '', $user_login, $user->ID);
		}
	}

	/**
	 * Logs wp_logout
	 * @return void
	 */
	public function log_wp_logout() {
		global $user_ID;
		$this->log('wp_logout', $user_ID);
	}

	/**
	 * Logs update_site_option - AKA network-wide settings
	 * @param  string $option   The name of the option that was changed.
	 * @param  mixed  $newvalue The new value for the option.
	 * @param  mixed  $oldvalue The old value for the option.
	 * @return void
	 */
	public function log_update_site_option($option, $newvalue, $oldvalue) {
		// ignore transients
		if (preg_match('/.*_transient_.*/i', $option)) return;
		// convert values to strings
		$oldvalue = (is_array($oldvalue) || is_object($oldvalue)) ? serialize($oldvalue) : $oldvalue;
		$newvalue = (is_array($newvalue) || is_object($newvalue)) ? serialize($newvalue) : $newvalue;
		$data = sprintf("(%s)->(%s)", $oldvalue, $newvalue);
		$site = get_current_site();
		$this->log('update_site_option', $site->id, $option, $data);
	}

	/**
	 * Logs updated_option - when a setting for a specific blog has changed.
	 * @param  string $option   The name of the option that was changed.
	 * @param  mixed  $newvalue The new value for the option.
	 * @param  mixed  $oldvalue The old value for the option.
	 * @return void
	 */
	public function log_updated_option($option, $oldvalue, $newvalue) {
		
		// ignore options matching these regexes
		$ignores = array(
			'/.*_transient_.*/i',
			'/.*user_roles$/i',
			'/^stats_cache$/i'
		);

		foreach ($ignores as $r) {
			if (preg_match($r, $option)) return;
		}

		// convert values to strings
		$oldvalue = (is_array($oldvalue) || is_object($oldvalue)) ? serialize($oldvalue) : $oldvalue;
		$newvalue = (is_array($newvalue) || is_object($newvalue)) ? serialize($newvalue) : $newvalue;
		$data = sprintf("(%s)->(%s)", $oldvalue, $newvalue);
		$blogId = get_current_blog_id();
		$this->log('updated_option', $blogId, $option, $data);
	}

	/**
	 * Logs user_register - when a new user is added to WordPress
	 * @param  int $userId The ID of the user.
	 * @return void
	 */
	public function log_user_register($userId) {
		$newUser = get_user_by('id', $userId);
		$this->log('user_register', $userId, $newUser->user_login);
	}

	/**
	 * Logs granted_super_admin - when Super Admin status is granted to a user.
	 * @param  int $userId The ID of the user.
	 * @return void
	 */
	public function log_granted_super_admin($userId) {
		$user = get_user_by('id', $userId);
		$this->log('granted_super_admin', $userId, $user->user_login);
	}

	/**
	 * Logs revoked_super_admin - when Super Admin status is revoked from a user.
	 * @param  int $userId The ID of the user.
	 * @return void
	 */
	public function log_revoked_super_admin($userId) {
		$user = get_user_by('id', $userId);
		$this->log('revoked_super_admin', $userId, $user->user_login);
	}

	/**
	 * Logs wpmu_upgrade_site - when a blog schema is updated during the upgrade process.
	 * @param  int $blogId The ID of the upgraded blog.
	 * @return void
	 */
	public function log_wpmu_upgrade_site($blogId) {
		$blog = get_blogs_details($blogId);
		$this->log('wpmu_upgrade_site', $blogId, $blog->path);
	}

	/**
	 * Logs wpmu_new_blog - when a new blog is added.
	 * @param  int $blogId The ID of the new blog.
	 * @param  int $userId The user ID of the new site's admin.
	 * @param  string $domain The new site's domain.
	 * @param  string $path The new site's path.
	 * @param  int $siteId The ID of the site that the blog belongs to.
	 * @param  array $meta Initial site options.
	 * @return void
	 */
	public function log_wpmu_new_blog($blogId, $userId, $domain, $path, $siteId, $meta) {
		$user = get_user_by('id', $userId);
		$data = 'admin:'.$user->user_login;
		$this->log('wpmu_new_blog', $blogId, $path, $data);
	}

	/**
	 * Logs delete_blog - when a blog is deleted.
	 * @param  int $blogId The ID of the deleted blog.
	 * @param  boolean $drop   True if the blog's tables are to be dropped, false if not.
	 * @return void
	 */
	public function log_delete_blog($blogId, $drop) {
		$doDrop = ($drop) ? 'drop_tables:true' : 'drop_tables:false';
		$this->log('delete_blog', $blogId, $doDrop);
	}

	/**
	 * Logs make_delete_blog - when a blog's status is set to deleted.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_make_delete_blog($blogId) {
		$this->log('make_delete_blog', $blogId);
	}

	/**
	 * Logs make_undelete_blog - when 'deleted' is removed from a blog's status.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_make_undelete_blog($blogId) {
		$this->log('make_undelete_blog', $blogId);
	}

	/**
	 * Logs archive_blog - when a blog is archived.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_archive_blog($blogId) {
		$this->log('archive_blog', $blogId);
	}

	/**
	 * Logs unarchive_blog - when a blog is unarchived.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_unarchive_blog($blogId) {
		$this->log('unarchive_blog', $blogId);
	}

	/**
	 * Logs mature_blog - when a blog is set to mature.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_mature_blog($blogId) {
		$this->log('mature_blog', $blogId);
	}

	/**
	 * Logs unmature_blog - when a blog is unmatured.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_unmature_blog($blogId) {
		$this->log('unmature_blog', $blogId);
	}

	/**
	 * Logs make_spam_blog - when a blog is set as spam.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_make_spam_blog($blogId) {
		$this->log('make_spam_blog', $blogId);
	}

	/**
	 * Logs make_ham_blog - when a blog is not spam.
	 * @param  int $blogId The ID of the blog.
	 * @return void
	 */
	public function log_make_ham_blog($blogId) {
		$this->log('make_ham_blog', $blogId);
	}

	/**
	 * Logs wpmu_delete_user - before a user has been deleted.
	 * @param  int $userId The user that was deleted.
	 * @return void
	 */
	public function log_wpmu_delete_user($userId) {
		$user = get_user_by('id', $userId);
		$this->log('wpmu_delete_user', $userId, $user->user_login);
	}

	/**
	 * Logs deleted_user - after a user has been deleted.
	 * @param  int $userId The user that was deleted.
	 * @return void
	 */
	public function log_deleted_user($userId) {
		$this->log('deleted_user', $userId);
	}

	/**
	 * Logs edit_user_profile_update - after a user's profile has been edited by someone else.
	 * 
	 * @param  int $userId The user that was edited.
	 * @return void
	 */
	public function log_edit_user_profile_update($userId) {
		$user = get_user_by('id', $userId);
		$this->log('edit_user_profile_update', $userId, $user->user_login);
	}

	/**
	 * Save an action to the log file
	 *
	 * @param string $action The action being performed.
	 * @param int $id The ID of the object associated with the action, like user ID, site ID, etc.
	 * @param string $title A title for the data (option key, blog title, etc.)
	 * @param string $data Data associated with the action (changed option values, etc.)
	 * @param int $userId The user ID (if different from the currently logged-in user)
	 * @return void
	 */
	protected function log($action, $id = '', $title = '', $data = '', $userId = '') {

		// consideration for reverse proxy situations
		$ip = 0;
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		} else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		if ($userId == '') {
			// currently-logged-in user
			global $user_ID;
			$user = get_user_by('id', $user_ID);
		} else {
			// specified user
			$user = get_user_by('id', $userId);
		}

		$date = new DateTime();

		// build the log string
		$items = array();
		$items[] = $date->format(DateTime::RFC2822);
		$items[] = $user->user_login.':'.$user->id;
		$items[] = $action;
		$items[] = $id;
		$items[] = $title;
		$items[] = $data;
		$items[] = $ip;
		$line = '['.implode('] [', $items).']';

		return $this->writeToLog($line);

	}

	/**
	 * Returns the full path to the log file.
	 * @return string The full path to the log file.
	 */
	protected function getLogfile() {
		return $this->logfile;
	}

	/**
	 * Sets the log file location.
	 * @param string $logfile The full path to the log file.
	 */
	protected function setLogfile($logfile) {
		$this->logfile = trim($logfile);
	}

	/**
	 * Appends the given line to the log file.
	 * @param  string $line The line to append to the log file.
	 * @return boolean Returns true on success, false otherwise.
	 */
	protected function writeToLog($line) {
		if ($this->validateLogfile($this->getLogfile()) === true) {
			$line = $this->removeNewLines($line) . "\n";
			return file_put_contents($this->getLogfile(), $line, FILE_APPEND);
		} else {
			return false;
		}
	}

	/**
	 * Removes new lines from a string.
	 * @param  string $s The string to modify.
	 * @return string The string with newlines removed.
	 */
	protected function removeNewLines($s) {
		return trim(preg_replace('/\s\s+/', ' ', $s));
	}

	/**
	 * Validate the location of the log file
	 * @param  string $filename The full path to the log file.
	 * @return boolean|string   Returns true if the filename is writable, or a string with an error message.
	 */
	protected function validateLogfile($filename) {

		if (file_exists($filename) && is_writable($filename)) {
			// file already exists and is writable
			return true;
		} else if (!file_exists($filename) && is_writable(dirname($filename))) {
			// file does not exist, but we should be able to create it
			return true;
		}

		switch ($filename) {
			case '':
				$error = 'There is no log filename specified.';
				break;
			case is_dir($filename):
				$error = 'The log filename cannot be a directory: ' . $filename;
				break;
			case (!file_exists($filename) && !is_writable(dirname($filename))):
				$error = 'The log file directory is not writable, so the log file cannot be created. You may need to create the file by hand with writable permissions for your web server.';
				break;
			case (file_exists($filename) && !is_writable($filename)):
				$error = 'The log file exists, but is not writable: ' . $filename;
				break;
			default:
				$error = 'Invalid log filename: ' . $filename;
		}

		return $error;

	}

	/**
	 * Adds the callback for the network admin settings page.
	 * @return void
	 */
	public function addOptionsPage() {
		add_submenu_page('settings.php', 'Observer', 'Observer', 'manage_network_options', 'observer', array(&$this, 'showOptionsPage'));
	}

	/**
	 * Displays the admin settings page.
	 * If a POST request, saves the submitted plugin options.
	 * @return void
	 */
	public function showOptionsPage() {

		if (!current_user_can('manage_network_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}

		echo '<div class="wrap"><h2>Observer</h2><h3>Network Super Admin Logging</h3>';
		echo '<p>Logs interesting actions performed by Super Admins.</p>';

		// is the current log filename valid?
		$isValid = $this->validateLogfile($this->getLogfile());
		if ($isValid !== true) {
			?>
			<div id="message-invalid-logfile" class="updated fade"><p><strong>Your log file has a problem:</strong> <?php echo htmlspecialchars($isValid); ?></p></div>
			<p>
				To start logging, you'll need to add a definition for the OBSERVER_LOG constant to your wp-config.php file.
				Enter the absolute path to the filename where you'd like to log Super Admin activity.
				For example, to log in the /var/log directory, add this line:
			</p>
			<pre>define('OBSERVER_LOG', '/var/log/wordpress_observer.log');</pre>
			<p>
				The filename specified should already exists and be writable by your web server, or the filename's parent directory must be writable by your web server.
				Observer will attempt to create the log file if it doesn't exist.
			</p>
			<?php
		} else {
			?>
			<p>Your log file is currently set to: <code><?php echo htmlspecialchars($this->getLogfile()); ?></code></p>
			<p>You can change it by modifying the OBSERVER_LOG constant in your wp-config.php file.</p>
			<?php
		}

		echo '</div>';

	}

	/**
	 * Returns the version number.
	 * @return string The version as a string in MAJOR.MINOR.REVISION format.
	 */
	public function getVersion() {
		return $this->version;
	}

}