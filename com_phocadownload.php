<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
 
defined('_JEXEC') or die;

if(JFile::exists(JPATH_ADMINISTRATOR . '/components/com_phocadownload/libraries/phocadownload/path/route.php')) {
	require_once JPATH_ADMINISTRATOR . '/components/com_phocadownload/libraries/phocadownload/path/route.php';
}else{
	require_once JPATH_SITE . '/components/com_phocadownload/helpers/route.php';
	final class PhocaDownloadRoute extends PhocaDownloadHelperRoute {} // workaround :)
}

final class xmap_com_phocadownload {
	
	private static $views = array('categories', 'category');
	
	public static function getTree(&$xmap, &$parent, &$params) {
		$uri = new JUri($parent->link);
		
		if(!in_array($uri->getVar('view'), self::$views)) {
			return;
		}
		
		$include_downloads = JArrayHelper::getValue($params, 'include_downloads');
		$include_downloads = ($include_downloads == 1 || ($include_downloads == 2 && $xmap->view == 'xml') || ($include_downloads == 3 && $xmap->view == 'html'));
		$params['include_downloads'] = $include_downloads;
		
		$show_unauth = JArrayHelper::getValue($params, 'show_unauth');
		$show_unauth = ($show_unauth == 1 || ( $show_unauth == 2 && $xmap->view == 'xml') || ( $show_unauth == 3 && $xmap->view == 'html'));
		$params['show_unauth'] = $show_unauth;
		
		$params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());
		
		$priority = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
		$changefreq = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);
		
		if($priority == -1) {
			$priority = $parent->priority;
		}
		
		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}
			
		$params['category_priority'] = $priority;
		$params['category_changefreq'] = $changefreq;
		
		$priority = JArrayHelper::getValue($params, 'download_priority', $parent->priority);
		$changefreq = JArrayHelper::getValue($params, 'download_changefreq', $parent->changefreq);
		
		if($priority == -1) {
			$priority = $parent->priority;
		}
		
		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}
		
		$params['download_priority'] = $priority;
		$params['download_changefreq'] = $changefreq;
		
		switch($uri->getVar('view')) {
			case 'categories':
				self::getCategoryTree($xmap, $parent, $params, 0);
			break;
					
			case 'category':
				self::getDownloads($xmap, $parent, $params, $uri->getVar('id'));
			break;					
		}
	}
	
	private static function getCategoryTree(&$xmap, &$parent, &$params, $parent_id) {
		$db = JFactory::getDbo();
		
		$query = $db->getQuery(true)
				->select(array('id', 'title', 'parent_id'))
				->from('#__phocadownload_categories')
				->where('parent_id = ' . $db->quote($parent_id))
				->where('published = 1')
				->order('ordering');
		
		if (!$params['show_unauth']) {
			$query->where('access IN(' . $params['groups'] . ')');
		}
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->title;
			$node->uid = $parent->uid . '_cid_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['category_priority'];
			$node->changefreq = $params['category_changefreq'];
			$node->pid = $row->parent_id;
			$node->link = PhocaDownloadRoute::getCategoryRoute($row->id);
			
			if ($xmap->printNode($node) !== false) {
				self::getCategoryTree($xmap, $parent, $params, $row->id);
				if ($params['include_downloads']) {
					self::getDownloads($xmap, $parent, $params, $row->id);
				}
			}
		}
		
		$xmap->changeLevel(-1);
	}

	private static function getDownloads(&$xmap, &$parent, &$params, $catid) {
		$db = JFactory::getDbo();
		$now = JFactory::getDate()->toSql();
		
		$query = $db->getQuery(true)
				->select(array('d.id', 'd.title'))
				->from('#__phocadownload AS d')
				->where('d.catid = ' . $db->Quote($catid))
				->where('d.published = 1')
				->where('(d.publish_up = ' . $db->quote($db->getNullDate()) . ' OR d.publish_up <= ' . $db->quote($now) . ')')
				->where('(d.publish_down = ' . $db->quote($db->getNullDate()) . ' OR d.publish_down >= ' . $db->quote($now) . ')')
				->order('d.ordering');
		
		if (!$params['show_unauth']) {
			$query->where('d.access IN(' . $params['groups'] . ')');
		}
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->title;
			$node->uid = $parent->uid . '_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['download_priority'];
			$node->changefreq = $params['download_changefreq'];
			$node->link = PhocaDownloadRoute::getFileRoute($row->id, $catid);
			
			$xmap->printNode($node);
		}
		
		$xmap->changeLevel(-1);
	}
}