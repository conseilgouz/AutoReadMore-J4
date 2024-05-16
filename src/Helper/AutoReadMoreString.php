<?php
/**
 * AutoReadMore plugin
 *
 * String helper class
 *
 * @from       https://github.com/gruz/AutoReadMore
 * @author     ConseilgGouz
 * @copyright (C) 2024 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 */
namespace ConseilGouz\Plugin\Content\Autoreadmore\Helper;
// No direct access
defined('_JEXEC') or die('Restricted access');
use Joomla\String\StringHelper;
/**
 * Truncate text preserving HTML tags with PHP
 *
 * Usage:
 * echo AutoReadMoreString::truncate('jo<i><b>n</b>as</i>', 3, '...'); //jo<...
 * echo AutoReadMoreString::truncate('jo<i><b>n</b>as</i>', 3, '...', true); //jo<i><b>n</b></i>...
 * echo AutoReadMoreString::truncate('jo<i><b>n</b>as</i>', 3, '...', true, false); //jo<i><b>n...
 *
 * @package		AutoReadMore
 * @author Jonas Raoni Soares Silva
 * @link http://snippets.dzone.com/posts/show/7125
 */
class AutoReadMoreString
{
	public static function truncate($text, $length, $suffix = '&hellip;', $isHTML = true, $noSpaceLanguage = false)
	{
		$i = 0;
		$simpleTags=array('br'=>true,'hr'=>true,'input'=>true,'image'=>true,'link'=>true,'meta'=>true);
		$tags = array();

		if($isHTML)
		{
			preg_match_all('/<[^>]+>([^<]*)/ui', $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

			foreach($matches as $match)
			{
				if($match[0][1] - $i >= $length)
				{
					break;
				}

				$t = StringHelper::substr(strtok($match[0][0], " \t\n\r\0\x0B>"), 1);

				// Test if the tag is unpaired, then we mustn't save them
				if($t[0] != '/' && (!isset($simpleTags[$t])))
				{
					$tags[] = $t;
				}
				elseif(end($tags) == StringHelper::substr($t, 1))
				{
					array_pop($tags);
				}
				$i += $match[1][1] - $match[0][1];
			}
		}

		// Output without closing tags
		$output = StringHelper::substr($text, 0, $length = min(StringHelper::strlen($text),  $length + $i));

		// closing tags
		$output2 = (count($tags = array_reverse($tags)) ? '</' . implode('></', $tags) . '>' : '');

		// Find last space or HTML tag (solving problem with last space in HTML tag eg. <span class="new">)
		$temp = preg_split('/<.*>| /ui', $output, -1, PREG_SPLIT_OFFSET_CAPTURE);

		$temp = end ($temp);
		$temp = end ($temp);

		$pos = (int)  $temp;

		// Append closing tags to output
		$output.=$output2;

		if ($noSpaceLanguage)
		{
			return $output;
		}

		// Get everything until last space
		$one = StringHelper::substr($output, 0, $pos);

		// Get the rest
		$two = StringHelper::substr($output, $pos, (StringHelper::strlen($output) - $pos));

		// Extract all tags from the last bit
		preg_match_all('/<(.*?)>/sui', $two, $tags);

		// Add suffix if needed
		if (StringHelper::strlen($text) > $length)
		{
			$one .= $suffix;
		}

		// Re-attach tags
		$output = $one . implode($tags[0]);

		//added to remove  unnecessary closure
		$output = str_replace('</!-->','',$output);

		return $output;
	}


	/**
	 * Tries to clean up HTML using either installed PHP extensions, or a custom class
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$text
	 * @return	string
	 */
	static function cleanUpHTML($text) {
		if (!function_exists('htmLawed')) { require_once (dirname(__FILE__).'/htmLawed.php'); }
		$text = preg_replace('/<[^>]*$/ui', '', $text);
		$text = htmLawed($text);
		return $text;
	}


	/**
	 * My function to truncate a text string with HTML tags by a number of words.
	 *
	 * The main magic is not to count tags as words. It returns broken HTML, need to be cleaned up.
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$text	text to be trucnated
	 * @param	int	$maxLimit	the number of words before truncating
	 * @param	bool	$show_readmore	pointer to the $article->readmore flag. Is set to true, if the text was truncated. A short text may be not truncated.
	 * @return	string			Trucated text with probably broken HTML
	 */
	static function truncateByWords($text, $maxLimit, &$show_readmore = false) {

		/* Some testing text
		$maxLimit = 10;
		$text1 = 'В тексті може бути тег, розрізаний по пробігах чи переносах рядків <a href="dfjds"
				title="sadsadas"> чи
				<span><div class="dasd"
				style="sadsa" >Here</div> </span> <br /> <br clear="both">
				<div
				rel="sas">AІва Рлд г
				</div>

				'.$text;
		$text = '<pre>1 2 3 4 5 6 7 8</pre> <p>aaaa</p> <b>das</b>'.$text;
		*/

		$text_prepare_temp = array();

		$show_readmore = false;
		$exploded_by_spaces = explode (' ', $text);
		$counter = 0;

		$openTags = 0;
		$closedTags = 0;

		foreach ($exploded_by_spaces as $exploded_by_spaces_element)
		{
			$exploded_by_linebreaks = explode(PHP_EOL, $exploded_by_spaces_element);

			foreach ($exploded_by_linebreaks as $exploded_by_linebreaks_element)
			{
				if ($counter >= $maxLimit)
				{
					$show_readmore = true;
					break;
				}

				$counter++;

				//if (trim($exploded_by_linebreaks_element) == '-') {
				$subject = $exploded_by_linebreaks_element;
				preg_match_all('/</', $subject, $matches, PREG_OFFSET_CAPTURE);
				$openTags =$openTags+count($matches[0]);
				preg_match_all('/>/', $subject, $matches, PREG_OFFSET_CAPTURE);
				$closedTags = count($matches[0]);
				$tagOpen = false;

				if ($openTags == 0)
				{
					//$tagOpen = false;
				}
				elseif ($openTags == $closedTags || ($openTags - $closedTags) > 0)
				{
					$tagOpen = true;
				}

				$openTags = $openTags - $closedTags;
				$strlen = null;
				$stripped = trim(strip_tags($exploded_by_linebreaks_element));

				if ($tagOpen && $closedTags > 0 && $stripped != '')
				{
					if (!StringHelper::strpos($stripped,'<') && !StringHelper::strpos($stripped,'>'))
					{
						$tagOpen = false;
					}
					else
					{
						$trim = trim(strip_tags($exploded_by_linebreaks_element));
						$strlen = StringHelper::strlen($trim)-1;

						if ($strlen == StringHelper::strpos($trim,'>'))
						{

						}
						else
						{
							$tagOpen = false;
						}
					}
				}

				if (
					empty ($exploded_by_linebreaks_element) ||
					(trim($exploded_by_linebreaks_element) == '-')
					|| $tagOpen
				)
				{
					$counter--;
				}

				$text_prepare_temp[] = $exploded_by_linebreaks_element;
			}

		}

		if ($show_readmore)
		{
			$text = implode(' ',$text_prepare_temp);
		}

		return $text;

	}

}
?>
