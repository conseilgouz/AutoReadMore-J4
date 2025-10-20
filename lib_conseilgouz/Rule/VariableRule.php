<?php
/**
 * ConseilGouz Custom Field CG Range for Joomla 4.x/5.x/6.x
 *
 * @author     ConseilgGouz
 * @copyright (C) 2025 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 *
 */

namespace ConseilGouz\Library\Rule;

defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

class VariableRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        // get showon to find field name
        $showon = (string)$element['showon'];
        $el = explode(':', $showon);
        $type = $el[0];
        $params = $input->get('params');
        if ($params->$type  == 'pick') { // color picker : exit
            return true;
        }
        if (!$value) {
            Factory::getApplication()->enqueueMessage(Text::_('CG_NOTEMPTY'), 'error');
            return false;
        }
        if (substr($value, 0, 2) != '--') {
            Factory::getApplication()->enqueueMessage(Text::_('CG_VARIABLE_START'), 'error');
            return false;
        }
        return true;

    }
}
