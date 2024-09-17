<?php
/**
 * AutoReadMore plugin
 *
 * @from       https://github.com/gruz/AutoReadMore
 * @author     ConseilgGouz
 * @copyright (C) 2024 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace ConseilGouz\Plugin\Content\Autoreadmore\Extension;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use ConseilGouz\Plugin\Content\Autoreadmore\Helper\AutoReadMoreString;

final class Autoreadmore extends CMSPlugin implements SubscriberInterface
{
    protected $plg_name;
    protected $plg_type;
    protected $plg_full_name;
    protected $plg_path_relative;
    protected $plg_path;
    protected $params_content;
    protected $fulltext_loaded;
    protected $trimming_dots;
    protected $alternative_readmore;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare'   => 'contentPrepare',
        ];
    }
    /**
     * Truncates the article text
     *
     * @param   string  $context   The context of the content being passed to the plugin.
     * @param   object  &$article  The article object
     * @param   object  &$params   The article params
     * @param   int     $page      Returns int 0 when is called not form an article, and empty when called from an article
     *
     * @return   void
     */
    public function contentPrepare($event)
    {
        if (!Factory::getApplication()->isClient('site')) {
            return false;
        }
        $context = $event[0];
        $article = $event[1];
        $params = $event[2];

        if (is_object($params) && ($params instanceof Registry) && $params->get("autoreadmore")) {
            return true;
        }

        $jinput = Factory::getApplication()->input;

        if ($jinput->get('option', null, 'CMD') == 'com_dump') {
            return;
        }

        // SOME SPECIAL RETURNS }
        $debug = $this->params->get('debug', false);

        if ($debug) {
            if (function_exists('dump') && false) {
                dump($article, 'context = ' . $context);
            } else {
                if ($debug == 1) {
                    Factory::getApplication()->enqueueMessage(
                        'Context : ' . $context . '<br />' .
                        'Title : ' . @$article->title . '<br />' .
                        'Id : ' . @$article->id . '<br />' .
                        'CatId : ' . @$article->catid . '<br />',
                        'warning'
                    );
                } elseif ($debug == 2) {
                    echo '<pre style="height:180px;overflow:auto;">';
                    echo '<b>Context : ' . $context . '</b><br />';
                    echo '<b>Content Item object : </b><br />';
                    print_r(json_decode(json_encode($article)));

                    if (!empty($params)) {
                        echo '<b>Params:</b><br />';
                        print_r(json_decode(json_encode($params)));
                    } else {
                        echo '<b>Params NOT passed</b><br />';
                    }

                    echo '</pre>' . PHP_EOL;
                }
            }
        }
        if ($context == "text") {
            return;
        } // it's not an article => ignore it

        $user = Factory::getApplication()->getIdentity();

        if ($context == 'com_tags.tag') {
            // $context = $article->type_alias;
            $article->catid = $article->core_catid;
            $article->id = $article->content_item_id;
            $article->slug = $article->id . ':' . $article->core_alias;
        }

        $thereIsPluginCode = false;

        if ($this->params->get('PluginCode', 'ignore') != 'ignore') {
            $possibleParams = array('text', 'introtext', 'fulltext');

            foreach ($possibleParams as $paramName) {
                if (isset($article->{$paramName}) && strpos($article->{$paramName}, '{autoreadmore}') !== false) {
                    $article->{$paramName} = str_replace(
                        array('{autoreadmore}', '<p>{autoreadmore}</p>', '<span>{autoreadmore}</span>'),
                        '',
                        $article->{$paramName}
                    );
                    $thereIsPluginCode = true;
                }
            }
        }
        if ($context == 'com_content.article') {
            // ignore existing read more + merge paragraphs => do it also in article context
            if ($this->params->get('Ignore_Existing_Read_More') && isset($article->introtext) && isset($article->fulltext)) {
                if ($this->params->get('Merge_After_Ignore', 0)) {// merge intro + fulltext => ignore last </p> intro, first <p> fulltext
                    $article->text = preg_replace('/<\/.*$/', '', $article->introtext).preg_replace('/<p[^>]*>/', '', $article->fulltext, 1);
                    return;
                }
            }
        }
        if (!$this->_checkIfAllowedContext($context, $article)) {
            return;
        }

        if (!$this->_checkIfAllowedCategoryAndItem($context, $article)) {
            return;
        }

        if (is_object($params) && ($params instanceof Registry)) {
            $this->params_content = $params;
        } elseif (is_array($params)) {
            $this->params_content = (object) $params;
        } else {
            $this->params_content = new Registry();
            // Load my plugin params.
            $this->params_content->loadString($params, 'JSON');
        }

        // Add css code
        $csscode = $this->params->get('csscode', '');
        if ($csscode) {
            /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
            $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
            $wa->addInlineStyle($csscode);
        }
        if (isset($article->introtext)) {
            // For core article
            $text = $article->introtext;
        } else {
            // In most non core content items and modules
            $text = $article->text;
        }
        // Fulltext is not loaded, we must load it manually if needed
        $this->fulltext_loaded = false;

        if ($this->params->get('Ignore_Existing_Read_More') && isset($article->introtext) && isset($article->fulltext)) {
            if ($this->params->get('Merge_After_Ignore', 0)) {// merge intro + fulltext => ignore last </p> intro, first <p> fulltext
                $text = preg_replace('/<\/.*$/', '', $article->introtext).preg_replace('/<p[^>]*>/', '', $article->fulltext, 1);
            } else {
                $text = $article->introtext . PHP_EOL . $article->fulltext;
            }
            if (file_exists(JPATH_PLUGINS ."/content/cck")) { //check if Seblod is installed
                $text = preg_replace('/::cck::(\d+)::\/cck::/', '', $text) ; //remove the ::cck:: tags
                $text = preg_replace('/::introtext::/', '', $text) ; //remove the ::introtext:: tags, keep content
                $text = preg_replace('/::\/introtext::/', '', $text) ; //remove the ::introtext:: tags, keep content
                $text = preg_replace('/::fulltext::/', '', $text) ; //remove the ::fulltext:: tags, keep content
                $text = preg_replace('/::\/fulltext::/', '', $text) ; //remove the ::fulltext:: tags, keep content
            }
            $this->fulltext_loaded = true;
        } elseif ($this->params->get('Ignore_Existing_Read_More') && isset($article->readmore) && $article->readmore > 0) {
            // If we ignore manual readmore and we know it's present, then we must load the full text
            $text .= $this->loadFullText($article->id);
            $this->fulltext_loaded = true;
        }
        if (PluginHelper::isEnabled('pagebuilderck', 'image')) { // Page builder CK Conflict
            $text = preg_replace('/\|URIROOT\|/', Uri::root(true), $text);
        }

        // apply content plugins
        if ($context == 'com_content.category') {
            PluginHelper::importPlugin('content');
            $myparams = clone $this->params_content;
            $myparams->set("autoreadmore", true);
            $item_cls = new \stdClass();
            $item_cls->text = $text;
            $item_cls->id = $article->id;
            Factory::getApplication()->triggerEvent('onContentPrepare', array($context, &$item_cls, &$myparams, 0));
            $text = $item_cls->text;
        }
        $ImageAsHTML = true;

        if (in_array($context, array("com_content.featured", "com_content.category"))) {
            $ImageAsHTML = $this->params->get('ImageAsHTML');
        }

        $thumbnails = $this->getThumbNails($text, $article, $context, $ImageAsHTML);

        // How many characters are we allowed?
        $app = Factory::getApplication();

        // Get current menu item number of leading articles
        // It's strange, but I couldn't call the variable as $params - it removes the header line in that case. I can't believe, but true.

        // So don't use $params = $app->getParams(); Either use another var name like $gparams = $app->getParams(); or the direct call
        // as shown below: $app->getParams()->def('num_leading_articles', 0);
        $num_leading_articles = $app->getParams()->def('num_leading_articles', 0);

        // Count how many times the plugin is called to know if the current article is a leading one
        $globalCount = $app->get('Count', 0, $this->plg_name);
        $globalCount++;
        $app->set('Count', $globalCount, $this->plg_name);

        // $GLOBALS['plg_content_AutoReadMore_Count'] = (isset($GLOBALS['plg_content_AutoReadMore_Count'])) ?
        // $GLOBALS['plg_content_AutoReadMore_Count']+1 : 1;

        // ~ if ($GLOBALS['plg_content_AutoReadMore_Count'] <= $num_leading_articles)
        if ($globalCount <= $num_leading_articles) {
            // This is a leading (full-width) article.
            $maxLimit = $this->params->get('leadingMax');
        } else {
            // This is not a leading article.
            $maxLimit = $this->params->get('introMax');
        }

        if (!is_numeric($maxLimit)) {
            $maxLimit = 500;
        }

        $this->trimming_dots = '';

        if ($this->params->get('add_trimming_dots') != 0) {
            $this->trimming_dots = $this->params->get('trimming_dots');
        }

        $limittype = $this->params->get('limittype');

        if (isset($article->readmore)) {
            $original_readmore = $article->readmore;
        }
        $noSpaceLanguage = $this->params->get('noSpaceLanguage');
        if ($limittype > 0) {
            $noSpaceLanguage = 0; // only apply if type = 0 (character)
        }
        switch ($this->params->get('PluginCode')) {
            case 'only':
                if (!$thereIsPluginCode) {
                    // Set a fake limit type if no truncate is needed
                    $limittype = -1;
                }
                break;
            case 'except':
                if ($thereIsPluginCode) {
                    // Set a fake limit type if no truncate is needed
                    $limittype = -1;
                }
                break;
            case 'ignore':
            default:
                break;
        }

        // Limit by chars
        if ($limittype == 0) {
            if (StringHelper::strlen(strip_tags($text)) > $maxLimit) {
                if ($this->params->get('Strip_Formatting') == 1) {
                    // First, remove all new lines
                    $text = preg_replace("/\r\n|\r|\n/", "", $text);

                    // Next, replace <br /> tags with \n
                    $text = preg_replace("/<BR[^>]*>/i", "\n", $text);

                    // Replace <p> tags with \n\n
                    $text = preg_replace("/<P[^>]*>/i", "\n\n", $text);

                    // Strip all tags
                    $text = strip_tags($text);

                    // Truncate
                    $text = StringHelper::substr($text, 0, $maxLimit);

                    // $text = String::truncate($text, $maxLimit, '...', true);
                    // Pop off the last word in case it got cut in the middle
                    $text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);

                    // Add ... to the end of the article.
                    $text = trim($text) . $this->trimming_dots;

                    // Replace \n with <br />
                    $text = str_replace("\n", "<br />", $text);
                } else {
                    // Truncate
                    // $text = StringHelper::substr($text, 0, $maxLimit);
                    $text = AutoReadMoreString::truncate($text, $maxLimit, '&hellip;', true, $noSpaceLanguage);

                    if (!$noSpaceLanguage) {
                        // Pop off the last word in case it got cut in the middle
                        $text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);
                    }

                    // Pop off the last tag, if it got cut in the middle.
                    $text = preg_replace('/<[^>]*$/', '', $text);

                    $text = $this->addTrimmingDots($text);

                    // Use Tidy to repair any bad XHTML (unclosed tags etc)
                    $text = AutoReadMoreString::cleanUpHTML($text);
                }
                // Add a "read more" link, makes sense only for com_content
                $article->readmore = true;
            }
        } elseif ($limittype == 1) { // Limit by words
            $original_length = StringHelper::strlen($text);
            $text = AutoReadMoreString::truncateByWords($text, $maxLimit, $article->readmore);
            $newLength = StringHelper::strlen($text);

            if ($newLength !== $original_length) {
                $article->readmore = true;
            }

            $text = $this->addTrimmingDots($text);
            $text = AutoReadMoreString::cleanUpHTML($text);
        } elseif ($limittype == 2) { // Limit by paragraphs
            $paragraphs = explode('</p>', $text);

            if (count($paragraphs) <= ($maxLimit + 1)) { // Do nothing, as we have $maxLimit paragraphs
            } else {
                $text = array();

                for ($i = 0; $i < $maxLimit; $i++) {
                    $text[] = $paragraphs[$i];
                }

                unset($paragraphs);
                $text = implode('</p>', $text);
                $article->readmore = true;
            }
        }

        if ($this->params->get('Strip_Formatting') == 1) {
            $text = strip_tags($text);
        }

        // If we have thumbnails, add it to $text.
        if ($this->params->get('Force_Image_Count')) {
            $text = preg_replace("/<img[^>]+\>/i", '', $text);
        }

        $text = $thumbnails . $text;

        if ($this->params->get('wrap_output') == 1) {
            $template = $this->params->get('wrap_output_template');
            $text = str_replace('%OUTPUT%', $text, $template);
        }

        //if ($this->params->get('readmore_text') && empty($this->alternative_readmore)) {
        if ($this->params->get('usertype', '0') && $user->guest) {
            if (isset($article->params)) {
                if ($this->_checkIfAllowedCategoryLogged($article)) {
                    $article->params->set('access-view', 0); // block access
                }
            }
            // note : Joomla uses COM_CONTENT_REGISTER_TO_READ_MORE in components/com_content/tmpl/category/default_articles.php
            // $article->alternative_readmore = Text::_($this->params->get('readmore_guest')); 
        }
        if ($this->params->get('readmore_text') && empty($this->alternative_readmore)) {
                $article->alternative_readmore = Text::_($this->params->get('readmore_text'));
        }
        $debug_addon = '';

        if ($debug) {
            $debug_addon = '<code>[DEBUG: AutoReadMore fired here]</code>';
        }
        // conflict with other content plugins
        if (isset($article->fulltext) && ($article->fulltext == "")) {
            $article->fulltext = $article->introtext;
        }
        $article->introtext = $text . $debug_addon;
        $article->text = $text . $debug_addon;
        if (isset($article->readmore) && !$article->readmore) {
            if (!$this->params->get('Ignore_Existing_Read_More') && isset($original_readmore)) {
                $article->readmore = $original_readmore;
            }
        }
    }

    /**
     * Checkis if the content item has to be parsed by the plugin
     *
     * @param   string  $context  Context
     * @param   object  $article  Content item object
     *
     * @return   bool  True if has to be parsed
     */
    public function _checkIfAllowedCategoryAndItem($context, $article)
    {
        if (!isset($article->catid) && !isset($article->id)) {
            return true;
        }

        $data = array();

        // Prepare data from joomla core articles or frontpage
        if (($this->params->get('joomla_articles')		&& 	$context == 'com_content.category')
            ||	($this->params->get('Enabled_Front_Page')	&& 	$context == 'com_content.featured')) {
            $prefix = '';

            if ($context == 'com_content.featured') {
                $prefix = 'fp_';
            }

            $row = array(
                'category_switch' => $this->params->get($prefix . 'categories_switch'),
                'category_ids' => $this->params->get($prefix . 'categories'),
                'item_switch' => $this->params->get($prefix . 'articles_switch'),
                'item_ids' => $this->params->get($prefix . 'id'),
            );
            $data[$context] = $row;
        }
        if ($this->params->get('joomla_articles')  &&  ($context == 'com_content.category')) {
            if (($this->params->get('joomla_articles_featured', 1) == 0) && ($article->featured == 1)) {
                // ignore featured items in category view
                return false;
            }
        }

        $context_switch = $this->params->get('context_switch');

        if ($context_switch == 'include') {
            $paramsContexts = $this->params->get('contextsToInclude');
            $contextsToInclude = json_decode($paramsContexts);

            // The default joomla installation procedure doesn't store defaut params into the DB in the correct way
            if (!empty($paramsContexts) && $contextsToInclude === null) {
                $paramsContexts = str_replace("'", '"', $paramsContexts);
                $contextsToInclude = json_decode($paramsContexts);
            }

            if (!empty($contextsToInclude) && !empty($contextsToInclude->context)) {
                foreach ($contextsToInclude->context as $k => $v) {
                    if ($v != $context) {
                        continue;
                    }

                    $row = array(
                        'category_switch' => $contextsToInclude->context_categories_switch[$k],
                        'category_ids' => $contextsToInclude->categories_ids[$k],
                        'item_switch' => $contextsToInclude->context_content_items_switch[$k],
                        'item_ids' => $contextsToInclude->context_content_item_ids[$k],
                    );
                    $data[$context] = $row;
                }
            }
        }

        if (empty($data[$context])) {
            return true;
        }

        $item_switch = $data[$context]['item_switch'];
        $item_ids = $data[$context]['item_ids'];

        if (!is_array($item_ids)) {
            $item_ids = array_map('trim', explode(',', $item_ids ?? ''));
        }

        $category_switch = $data[$context]['category_switch'];
        $category_ids = $data[$context]['category_ids'];

        if (!is_array($category_ids)) {
            $category_ids = array_map('trim', explode(',', $category_ids ?? ''));
        }

        switch ($item_switch) {
            // Some articles are selected
            case '1':
                if (in_array($article->id, $item_ids)) {
                    return true;
                }
                break;

                // Some articles are excluded
            case '2':
                // If the article is among the excluded ones - return false
                if (in_array($article->id, $item_ids)) {
                    return false;
                }
                break;

                // No specific articles set
            case '0':
            default:
                break;
        }

        $in_array = in_array($article->catid, $category_ids);

        switch ($category_switch) {
            // ALL CATS
            case '0':
                return true;
                break;

                // Selected cats
            case '1':
                if ($in_array) {
                    return true;
                }

                return false;
                break;

                // Excludes cats
            case '2':
                if ($in_array) {
                    return false;
                }

                return true;
                break;
            default:
                break;
        }

        return true;
    }
    /**
     * Checkis if the content item is allowed in logged only mode
     *
     * @param   object  $article  Content item object
     *
     * @return   bool  True if has to be parsed
     */
    public function _checkIfAllowedCategoryLogged($article)
    {
        $category_switch = $this->params->get('log_categories_switch');
        $category_ids = $this->params->get('log_categories',array());

        $in_array = in_array($article->catid, $category_ids);

        switch ($category_switch) {
            // ALL CATS
            case '0':
                return true;
                break;

                // Selected cats
            case '1':
                if ($in_array) {
                    return true;
                }

                return false;
                break;

                // Excludes cats
            case '2':
                if ($in_array) {
                    return false;
                }

                return true;
                break;
            default:
                break;
        }

        return true;
    }
    /**
     * Check if current context is allowed either by settings or by some hardcoded rules
     *
     * @param   string  $context  Context passed to the onContentPrepare method
     * @param   object  $article  Content item object
     *
     * @return  bool  true if allowed, false otherwise
     */
    public function _checkIfAllowedContext($context, $article)
    {
        $jinput = Factory::getApplication()->input;
        $context_global = explode('.', $context);
        $context_global = $context_global[0];

        // Some hard-coded contexts to exclude
        $hard_coded_exclude_global_contexts = array(
            'com_virtuemart', // Never fire for VirtueMart
        );

        $contextsToExclude = array(
            'com_tz_portfolio.p_article', // Never run for full article
            'com_content.article', // Never run for full article

            // 'mod_custom.content', // never run at a custom HTML module - DISABLED here,
            // because the user must be allowed to choose this. At some circumstances joomla HTML modules may be needed to cut
        );

        if (in_array($context_global, $hard_coded_exclude_global_contexts) || in_array($context, $contextsToExclude)) {
            return false;
        }

        // SOME SPECIAL RETURNS {
        // Fix easyblog
        if ($context == 'easyblog.blog' && $jinput->get('view', null, 'CMD') == 'entry') {
            return false;
        }

        $view = $jinput->get('view', null, 'CMD');
        $article_id = $jinput->get('id', null, 'INT');

        if (isset($article->id)) {
            if (($view == "article" && $article->id == $article_id)
                || ($context == 'com_k2.item' && $article->id == $article_id)) {
                // If it's already a full article - go away'
                if (!isset($GLOBALS['joomlaAutoReadMorePluginArticleShown'])) {
                    // But leave a flag not to go aways in a module
                    $GLOBALS['joomlaAutoReadMorePluginArticleShown'] = $article_id;

                    return false;
                }
            }
        }

        if ($this->params->get('Enabled_Front_Page') == 0 and $context == 'com_content.featured') {
            return false;
        } elseif ($this->params->get('Enabled_Front_Page') == 1 and $context == 'com_content.featured') {
            return true;
        }

        if ($this->params->get('joomla_articles') == 0 and $context == 'com_content.category') {
            return false;
        } elseif ($context == 'com_content.categories') {
            if ($this->params->get('joomla_articles_parse_category')) {
                return true;
            } else {
                return false;
            }
        } elseif ($this->params->get('joomla_articles') == 1 and in_array($context, ['com_content.category', 'com_content.categories'])) {
            // If it's an article, as a category desc doesn't contain anything in it's object except ->text
            if (isset($article->id)) {
                return true;
            } else {
                if ($this->params->get('joomla_articles_parse_category')) {
                    return true;
                } else {
                    return false;
                }
            }
        }

        $context_switch = $this->params->get('context_switch');

        switch ($context_switch) {
            case 'include':
                $paramsContexts = $this->params->get('contextsToInclude');
                $contextsToInclude = json_decode($paramsContexts);

                // The default joomla installation procedure doesn't store defaut params into the DB in the correct way
                if (!empty($paramsContexts) && $contextsToInclude === null) {
                    $paramsContexts = str_replace("'", '"', $paramsContexts);
                    $contextsToInclude = json_decode($paramsContexts);
                }

                if (!empty($contextsToInclude) && !empty($contextsToInclude->context)) {
                    foreach ($contextsToInclude->context as $k => $v) {
                        if ($context == $v) {
                            return true;
                        }
                    }
                }

                return false;
            case 'exclude':
                // Not to work on modules, like mod_roksprocket.article
                if ($this->params->get('exclude_mod_contexts') && strpos($context, 'mod_') === 0) {
                    return false;
                }

                $contextsToExclude = $this->params->get('contextsToExclude');
                $contextsToExclude = array_map('trim', explode(",", $contextsToExclude));

                if (in_array($context, $contextsToExclude)) {
                    return false;
                }
                break;
            case 'all_enabled':
                return true;
                break;
            case 'all_disabled':
                // This check just in case, should never come here in such a case
                if (!in_array($context, array('com_content.category', 'com_content.featured'))) {
                    return false;
                }
                break;
            default:
                break;
        }

        return true;
    }

    /**
     * Add Trimming dots
     *
     * @param   string  $text  Text to add trimming symbol(s)
     *
     * @return  string  Text with added trimming symbol(s)
     */
    public function addTrimmingDots($text)
    {
        // Add ... to the end of the article if the last character is a letter or a number.
        if ($this->params->get('add_trimming_dots') == 2) {
            if (preg_match('/\w/ui', StringHelper::substr($text, -1))) {
                $text = trim($text) . $this->trimming_dots;
            }
        } else {
            $text = trim($text) . $this->trimming_dots;
        }

        return $text;
    }

    /**
     * Returns the full text of the article based on the article id
     *
     * @param   integer  $id  The id of the artice to load
     *
     * @return   string  The article fulltext
     */
    public function loadFullText($id)
    {
        $article = Table::getInstance("content", 'JTable');
        $article->load($id);

        $article->fulltext_loaded = true;

        return $article->fulltext;
    }

    /**
     * Returns text with handled images - added classes, stripped attributes, if needed
     *
     * @param   string  &$text        HTML code of the article
     * @param   object  &$article     Article object for additional information like $article->id
     * @param   text    $context      Context
     * @param   bool    $ImageAsHTML  If to return html code or to update the $article object
     *
     * @return   text  HTML code to include containing images
     */
    public function getThumbNails(& $text, & $article, $context, $ImageAsHTML = true)
    {
        $user = Factory::getApplication()->getIdentity();
        // Are we working with any thumbnails?
        if ($this->params->get('Thumbnails') < 1) {
            return;
        }

        $thumbnails = array();

        switch ($this->params->get('image_search_pattern')) {
            case 'custom':
                $patterns = explode(PHP_EOL, $this->params->get('image_search_pattern_custom'));
                $patterns = array_map('trim', $patterns);
                break;
            case 'a_wrapped':
                $patterns = ['~<a[^>]+><img [^>]+></a>~ui'];
                break;
            case 'img_only':
            default:
                $patterns = ['~<img [^>]*>~iu'];
                break;
        }

        $totalThumbNails = $this->params->get('Thumbnails');

        // ~ $patterns = [
        // ~ '/<img [^>]*>/iu',
        // ~ '~<a[^>]+><img [^>]+></a>~ui',
        // ~ ];

        $total_matches = [];

        $fulltext = '';

        foreach ($patterns as $pattern) {
            // Extract all images from the article.
            $imagesfound  = preg_match_all($pattern, $text, $matches);

            // If we found less thumbnail then expected and the fulltext is not loaded,
            // then load fulltext and search in it also
            $matches_tmp = array();

            $json = json_decode($article->images);

            if (!empty($json->image_intro)) {
                $totalThumbNails--;
            }

            if ($totalThumbNails < 0) {
                $totalThumbNails = 0;
            }

            if ($imagesfound < $totalThumbNails && empty($fulltext)) {

                if (isset($article->fulltext)) {
                    $fulltext = $article->fulltext;
                } elseif(isset($article->id) && !$this->fulltext_loaded && in_array($context, array('com_content.category','com_content.featured'))) {
                    $this->loadFullText($article->id);
                }

                $matches_tmp = $matches[0];
                $imagesfound  = preg_match_all($pattern, $fulltext, $matches);
            }

            $matches = array_merge($matches_tmp, $matches[0]);


            foreach ($matches as $km => $match) {
                $placeholder = '// ##mygruz20170704012529###' . $km . '###// ##mygruz20170704012529';
                $text = str_replace($match, $placeholder, $text);
                $fulltext = str_replace($match, $placeholder, $fulltext);

                if (!in_array($match, $total_matches)) {
                    $total_matches[$placeholder] = $match;
                }
            }
        }

        $matches = [];

        foreach ($total_matches as $placeholder => $match) {
            $text = str_replace($placeholder, $match, $text);

            $matches[] = $match;
        }

        // Loop through the thumbnails.
        for ($thumbnail = 0; $thumbnail < $totalThumbNails; $thumbnail++) {
            if (!isset($matches[$thumbnail])) {
                break;
            }

            // Remove the image from $text
            $text = str_replace($matches[$thumbnail], '', $text);

            // See if we need to remove styling.
            if (trim($this->params->get('Thumbnails_Class', '')) != '') {
                // Remove style, class, width, border, and height attributes.
                if ($this->params->get('Strip_Image_Formatting')) {
                    // Add CSS class name.
                    $matches[$thumbnail] = preg_replace('/(style|class|width|height|border) ?= ?[\'"][^\'"]*[\'"]/i', '', $matches[$thumbnail]);
                    $matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->params->get('Thumbnails_Class') . '" />', $matches[$thumbnail]);
                } else {
                    $matches[$thumbnail] = preg_replace('@(class=["\'])@', '$1' . $this->params->get('Thumbnails_Class') . ' ', $matches[$thumbnail], -1, $count);

                    if ($count < 1) {
                        $matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->params->get('Thumbnails_Class') . '" />', $matches[$thumbnail]);
                    }
                }
            }

            if (trim($matches[$thumbnail]) != '') {
                if ($ImageAsHTML) {
                    $thumbnails[] = $matches[$thumbnail];
                } elseif ($thumbnail === 0) {
                    // Just flag for later see if there was at least one image
                    $thumbnails[] = '';

                    foreach (array('image_intro' => 'src', 'image_intro_alt' => 'alt',  'image_intro_caption' => 'title') as $k => $v) {
                        $match = null;
                        preg_match('@' . $v . '="([^"]+)"@', $matches[$thumbnail], $match);

                        // ${$v} = array_pop($match);
                        $json->{$k} = array_pop($match);
                    }
                }
            }
        }

        if (empty($thumbnails) && trim($this->params->get('default_image', '')) != '') {
            $Thumbnails_Class = $this->params->get('Thumbnails_Class');
            $Thumbnails_Class_Check = trim($Thumbnails_Class);

            if (!empty($Thumbnails_Class_Check)) {
                $Thumbnails_Class = ' class="' . $Thumbnails_Class . '"';
            } else {
                $Thumbnails_Class = '';
            }

            if ($ImageAsHTML) {
                $thumbnails[] = '<img ' . $Thumbnails_Class . ' src="' . $this->params->get('default_image') . '">';
            } elseif (empty($json->image_intro)) {
                $json->image_intro = $this->params->get('default_image');
            }
        }

        if (!$ImageAsHTML) {
            $article->images = json_encode($json);
        }

        // Make this thumbnail a link.
        // $matches[$thumbnail] = "<a href='" . $link . "'>{$matches[$thumbnail]}</a>";

        // Add to the list of thumbnails.
        if ($this->params->get('image_link_to_article') && $ImageAsHTML) {
            $jinput = Factory::getApplication()->input;

            while (true) {
                $option = $jinput->get('option', null, 'CMD');

                // Do not create link for K2, VM and so on
                if (in_array(
                    $option,
                    array(
                            'com_k2',
                            'com_virtuemart'
                        )
                )) {
                    if (!empty($article->link)) {
                        $link = $article->link;
                    }

                    break;
                }

                if (!isset($article->catid)) {
                    break;
                }

                if (!isset($article->slug)) {
                    $article->slug = '';
                }

                if (isset($article->router) && isset($article->catid)) {
                    $link = Route::_(call_user_func($article->router, $article->slug, $article->catid));
                    break;
                }

                if (isset($article->link)) {
                    $link = $article->link;
                    $link = Route::_($link);
                    break;
                }

                // Prepare the article link
                if ($this->params_content->get('access-view')) {
                    $link = Route::_(RouteHelper::getArticleRoute($article->slug, $article->catid));
                } else {
                    $menu = Factory::getApplication()->getMenu();
                    $active = $menu->getActive();

                    if (!isset($active->id)) {
                        $active = $menu->getDefault();
                    }

                    $itemId = $active->id;
                    $link1 = Route::_('index.php?option=com_users&view=login&Itemid=' . $itemId);
                    $returnURL = Route::_(RouteHelper::getArticleRoute($article->slug, $article->catid));
                    $link = new URI($link1);
                    $link->setVar('return', base64_encode($returnURL));
                }
                break;
            }

            /* @todo : required login, block visitor
            if ($this->params->get('usertype', '0') && $user->guest) {
                $link = "index.php?option=com_users&view=login";
            } */
            if (isset($link)) {
                foreach ($thumbnails as $k => $v) {
                    $thumbnails[$k] = '<a href="' . $link . '">' . $v . '</a>';
                }
            }
        }

        if ($this->params->get('Force_Image_Count')) {
            if (!sizeof($thumbnails) && empty($json->image_intro) && !empty($json->image_fulltext)) {
                $json->image_intro = $json->image_fulltext;
                $article->images = json_encode($json);
            }
            foreach ($thumbnails as $k => $v) {
                if ($k > ($totalThumbNails - 1)) {
                    unset($thumbnails[$k]);
                }
            }
        }

        if ($this->params->get('wrap_image_output') == 1) {
            $template = $this->params->get('wrap_image_output_template');
            foreach ($thumbnails as $kk => $vv) {
                $thumbnails[$kk] = str_replace('%OUTPUT%', $vv, $template);
            }
        }

        return implode(PHP_EOL, $thumbnails);
    }
}
