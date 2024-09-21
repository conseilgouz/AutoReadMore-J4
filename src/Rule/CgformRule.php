<?php
/**
 * AutoReadMore plugin
 *
 * @from       https://github.com/gruz/AutoReadMore
 * @author     ConseilgGouz
 * @copyright (C) 2024 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace ConseilGouz\Plugin\Content\Autoreadmore\Rule;

defined( '_JEXEC' ) or die( 'Restricted access' );
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\Rule\SubformRule;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

class CgformRule extends SubformRule
{
	public function test(\SimpleXMLElement $element, $value, $group = null, Registry $input = null, Form $form = null)
    {
		$checks = $value;
		$languages = LanguageHelper::getLanguages();
		$langs = [];
		foreach($checks as $one) {
			if (in_array($one->readmore_list_lang,$langs)) {
				Factory::getApplication()->enqueueMessage(
					Text::sprintf('PLG_CONTENT_AUTOREADMORE_READMORE_LIST_ERR',$one->readmore_list_lang),
					'error');
				return false;
			}
			$langs[] = $one->readmore_list_lang;
		}
		return true;
		
	}

}