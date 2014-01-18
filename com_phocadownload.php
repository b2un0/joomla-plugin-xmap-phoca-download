<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 - 2014 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

if (JComponentHelper::isEnabled('com_phocadownload') && !class_exists('PhocaDownloadRoute')) {
    if (JFile::exists(JPATH_ADMINISTRATOR . '/components/com_phocadownload/libraries/phocadownload/path/route.php')) {
        require_once JPATH_ADMINISTRATOR . '/components/com_phocadownload/libraries/phocadownload/path/route.php';
    } else {
        require_once JPATH_SITE . '/components/com_phocadownload/helpers/route.php';

        final class PhocaDownloadRoute extends PhocaDownloadHelperRoute
        {
        } // workaround :)
    }
}

final class xmap_com_phocadownload
{

    private static $views = array('categories', 'category');
    private static $enabled = false;

    public function __construct()
    {
        self::$enabled = JComponentHelper::isEnabled('com_phocadownload');
    }

    public static function getTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params)
    {
        $uri = new JUri($parent->link);

        if (!self::$enabled || !in_array($uri->getVar('view'), self::$views)) {
            return;
        }

        $params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

        $params['language_filter'] = JFactory::getApplication()->getLanguageFilter();

        $params['include_downloads'] = JArrayHelper::getValue($params, 'include_downloads', 1);
        $params['include_downloads'] = ($params['include_downloads'] == 1 || ($params['include_downloads'] == 2 && $xmap->view == 'xml') || ($params['include_downloads'] == 3 && $xmap->view == 'html'));

        $params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
        $params['show_unauth'] = ($params['show_unauth'] == 1 || ($params['show_unauth'] == 2 && $xmap->view == 'xml') || ($params['show_unauth'] == 3 && $xmap->view == 'html'));

        $params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
        $params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);

        if ($params['category_priority'] == -1) {
            $params['category_priority'] = $parent->priority;
        }

        if ($params['category_changefreq'] == -1) {
            $params['category_changefreq'] = $parent->changefreq;
        }

        $params['download_priority'] = JArrayHelper::getValue($params, 'download_priority', $parent->priority);
        $params['download_changefreq'] = JArrayHelper::getValue($params, 'download_changefreq', $parent->changefreq);

        if ($params['download_priority'] == -1) {
            $params['download_priority'] = $parent->priority;
        }

        if ($params['download_changefreq'] == -1) {
            $params['download_changefreq'] = $parent->changefreq;
        }

        switch ($uri->getVar('view')) {
            case 'categories':
                self::getCategoryTree($xmap, $parent, $params, 0);
                break;

            case 'category':
                self::getDownloads($xmap, $parent, $params, $uri->getVar('id'));
                break;
        }
    }

    private static function getCategoryTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $parent_id)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select(array('c.id', 'c.alias', 'c.title', 'c.parent_id'))
            ->from('#__phocadownload_categories AS c')
            ->where('c.parent_id = ' . $db->quote($parent_id))
            ->where('c.published = 1')
            ->order('c.ordering');

        if (!$params['show_unauth']) {
            $query->where('c.access IN(' . $params['groups'] . ')');
        }

        if ($params['language_filter']) {
            $query->where('c.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row) {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->uid = $parent->uid . '_cid_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['category_priority'];
            $node->changefreq = $params['category_changefreq'];
            $node->pid = $row->parent_id;
            $node->link = PhocaDownloadRoute::getCategoryRoute($row->id . ':' . $row->alias);

            if ($xmap->printNode($node) !== false) {
                self::getDownloads($xmap, $parent, $params, $row->id);
            }
        }

        $xmap->changeLevel(-1);
    }

    private static function getDownloads(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $catid)
    {
        self::getCategoryTree($xmap, $parent, $params, $catid);

        if (!$params['include_downloads']) {
            return;
        }

        $db = JFactory::getDbo();
        $now = JFactory::getDate('now', 'UTC')->toSql();

        $query = $db->getQuery(true)
            ->select(array('d.id', 'd.alias', 'd.title'))
            ->from('#__phocadownload AS d')
            ->where('d.catid = ' . $db->Quote($catid))
            ->where('d.published = 1')
            ->where('(d.publish_up = ' . $db->quote($db->getNullDate()) . ' OR d.publish_up <= ' . $db->quote($now) . ')')
            ->where('(d.publish_down = ' . $db->quote($db->getNullDate()) . ' OR d.publish_down >= ' . $db->quote($now) . ')')
            ->order('d.ordering');

        if (!$params['show_unauth']) {
            $query->where('d.access IN(' . $params['groups'] . ')');
        }

        if ($params['language_filter']) {
            $query->where('d.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row) {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->uid = $parent->uid . '_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['download_priority'];
            $node->changefreq = $params['download_changefreq'];
            $node->link = PhocaDownloadRoute::getFileRoute($row->id . ':' . $row->alias);

            $xmap->printNode($node);
        }

        $xmap->changeLevel(-1);
    }
}