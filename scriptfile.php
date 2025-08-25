<?php
/**
 * AutoReadMore plugin
 *
 * @from       https://github.com/gruz/AutoReadMore
 * @author     ConseilgGouz
 * @copyright (C) 2025 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access to this file
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class plgContentAutoreadmoreInstallerScript  {
	
	private $extname                 = 'autoreadmore';	
	private $min_joomla_version      = '4.0.0';
	private $min_php_version         = '8.0';
	private $installerName = 'ContentAutoreadmoreInstaller';
	
	private $dir;
	private $lang;
	
	public function __construct()
	{
		$this->dir = __DIR__;
		$this->lang = Factory::getLanguage();
		$this->lang->load($this->extname);
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent) {
		if ( ! $this->passMinimumJoomlaVersion())
		{
			$this->uninstallInstaller();
			return false;
		}

		if ( ! $this->passMinimumPHPVersion())
		{
			$this->uninstallInstaller();
			return false;
		}
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight($type, $parent) {
		if (($type=='install') || ($type == 'update')) { // remove obsolete dir/files
			$this->postinstall_cleanup();
			$this->enable_plugin();
		}
		
		return true;
    }
	
	private function postinstall_cleanup() {
				$obsloteFolders = ['extensions', 'images','language','helpers'];
		// Remove plugins' files which load outside of the component. If any is not fully updated your site won't crash.
		foreach ($obsloteFolders as $folder)
		{
			$f = JPATH_SITE . '/plugins/content/'.$this->extname.'/' . $folder;

			if (!@file_exists($f) || !is_dir($f) || is_link($f))
			{
				continue;
			}

			Folder::delete($f);
		}
		$obsloteFiles = [sprintf("%s/plugins/content/%s/scriptary.php", JPATH_SITE, $this->extname), sprintf("%s/plugins/content/%s/scriptfile.php", JPATH_SITE, $this->extname)];
		foreach ($obsloteFiles as $file)
		{
			if (@is_file($file))
			{
				File::delete($file);
			}
		}
	}	
	private function enable_plugin() {
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = ' . $db->quote(1))
			->where($db->quoteName('element') . ' = ' . $db->quote($this->extname));
		$db->setQuery($query);
		
		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			echo Text::_('Error while enabling '.$this->extname);
			
			return;
		}
		
	}

	// Check if Joomla version passes minimum requirement
	private function passMinimumJoomlaVersion()
	{
		$j = new Version();
		$version=$j->getShortVersion(); 
		if (version_compare($version, $this->min_joomla_version, '<'))
		{
            if ($j->getHelpVersion() == '.310') {
                Factory::getApplication()->enqueueMessage(
                    'Incompatible Joomla version : found <strong>' . $version . '</strong>, Minimum : <strong>' . $this->min_joomla_version . '</strong><br><br>Please download <a href="https://github.com/conseilgouz/AutoReadMore-J4/releases/download/5.1.5/AutoReadMore-J4-5.1.5.zip" download="AutoReadMore-J4-5.1.5.zip">AutoreadMore version 5.1.5</a> for Joomla! 3.10.<br><br>',
                    'error'
                );
            } else {
                Factory::getApplication()->enqueueMessage(
                    'Incompatible Joomla version : found <strong>' . $version . '</strong>, Minimum : <strong>' . $this->min_joomla_version . '</strong>',
                    'error'
                );
            }
			return false;
		}

		return true;
	}

	// Check if PHP version passes minimum requirement
	private function passMinimumPHPVersion()
	{

		if (version_compare(PHP_VERSION, $this->min_php_version, '<'))
		{
			Factory::getApplication()->enqueueMessage(
					'Incompatible PHP version : found  <strong>' . PHP_VERSION . '</strong>, Minimum <strong>' . $this->min_php_version . '</strong>',
				'error'
			);
			return false;
		}

		return true;
	}
	private function uninstallInstaller()
	{
		if ( ! is_dir(JPATH_PLUGINS . '/system/' . $this->installerName)) {
			return;
		}
		$this->delete([
			JPATH_PLUGINS . '/system/' . $this->installerName . '/language',
			JPATH_PLUGINS . '/system/' . $this->installerName,
		]);
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->delete('#__extensions')
			->where($db->quoteName('element') . ' = ' . $db->quote($this->installerName))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));
		$db->setQuery($query);
		$db->execute();
		Factory::getCache()->clean('_system');
	}
    public function delete($files = [])
    {
        foreach ($files as $file) {
            if (is_dir($file)) {
                Folder::delete($file);
            }

            if (is_file($file)) {
                File::delete($file);
            }
        }
    }

}
?>

