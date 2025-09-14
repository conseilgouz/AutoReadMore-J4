<?php
/**
 * ConseilGouz Custom Field CG Range for Joomla 4.x/5.x/6.x
 *
 * @author     ConseilgGouz
 * @copyright (C) 2025 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;
use Joomla\CMS\Factory;

extract($displayData);


/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('cgrange', 'media/conseilgouz/fields/css/cgrange.css');
$wa->registerAndUseScript('cgrange', 'media/conseilgouz/fields/js/cgrange.js');

/**
 * Layout variables
 * -----------------
 * @var   string   $autocomplete    Autocomplete attribute for the field.
 * @var   boolean  $autofocus       Is autofocus enabled?
 * @var   string   $class           Classes for the input.
 * @var   string   $description     Description of the field.
 * @var   boolean  $disabled        Is this field disabled?
 * @var   string   $group           Group the field belongs to. <fields> section in form XML.
 * @var   boolean  $hidden          Is this field hidden in the form?
 * @var   string   $hint            Placeholder for the field.
 * @var   string   $id              DOM id of the field.
 * @var   string   $label           Label of the field.
 * @var   string   $labelclass      Classes to apply to the label.
 * @var   boolean  $multiple        Does this field support multiple values?
 * @var   string   $name            Name of the input field.
 * @var   string   $onchange        Onchange attribute for the field.
 * @var   string   $onclick         Onclick attribute for the field.
 * @var   string   $pattern         Pattern (Reg Ex) of value of the form field.
 * @var   boolean  $readonly        Is this field read only?
 * @var   boolean  $repeat          Allows extensions to duplicate elements.
 * @var   boolean  $required        Is this field required?
 * @var   integer  $size            Size attribute of the input.
 * @var   boolean  $spellcheck      Spellcheck state for the form field.
 * @var   string   $validate        Validation rules to apply.
 * @var   string   $value           Value attribute of the field.
 * @var   array    $checkedOptions  Options that will be set as checked.
 * @var   boolean  $hasValue        Has this field a value assigned?
 * @var   array    $options         Options available for this field.
 * @var   array    $inputType       Options available for this field.
 * @var   string   $accept          File types that are accepted.
 * @var   string   $dataAttribute   Miscellaneous data attributes preprocessed for HTML output
 * @var   array    $dataAttributes  Miscellaneous data attribute for eg, data-*.
 */

// Initialize some field attributes.
$attributes = [
    $class ? 'class="form-cgrange ' . $class . '"' : 'class="form-cgrange"',
    !empty($description) ? 'aria-describedby="' . ($id ?: $name) . '-desc"' : '',
    $disabled ? 'disabled' : '',
    $readonly ? 'readonly' : '',
    !empty($onchange) ? 'onchange="' . $onchange . '"' : '',
    !empty($max) ? 'max="' . $max . '"' : '',
    !empty($step) ? 'step="' . $step . '"' : '',
    !empty($min) ? 'min="' . $min . '"' : '',
    !empty($unit) ? 'unit="' . $unit . '"' : '',
    $autofocus ? 'autofocus' : '',
    $dataAttribute,
];

$value = is_numeric($value) ? (float) $value : $min;
// CG Range : display current value after range
//             add class="limits" to display range limits
//             add class="buttons" to display reset, + , - buttons 
?>
<div style="display:flex">
<div>
<input
    type="range"
    name="<?php echo $name; ?>"
    id="<?php echo $id; ?>"
    value="<?php echo htmlspecialchars($value, ENT_COMPAT, 'UTF-8'); ?>"
    <?php echo implode(' ', $attributes); ?>>
<?php if (strpos($class, 'buttons') !== false) { ?>
<div class="rangebuttons text-center">
<span id="cgrange-minus-<?php echo $id;?>" class="cgrange-minus" data="<?php echo $id;?>" style="margin-left:1em">&nbsp;-&nbsp;</span>
<span id="cgrange-reset-<?php echo $id;?>" class="cgrange-reset" data="<?php echo $id;?>" style="margin-left:.2em" >Reset</span>
<span id="cgrange-plus-<?php echo $id;?>" class="cgrange-plus" data="<?php echo $id;?>"  style="margin-left:.2em" >&nbsp;+&nbsp;</span>
</div>
<?php } ?>
</div>
<span id="cgrange-label-<?php echo $id;?>" class="cgrange-label" data="<?php echo $id;?>" style="margin-left:1em"></span>

</div>