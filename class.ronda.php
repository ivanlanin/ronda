<?php
/**
 * mods:
 * rc: recent changes (perubahan terbaru)
 * pr: pending revision (suntingan tunda)
 * dr: deletion request (permintaaan hapus)
 *
 * 2011-03-08 11:02
 */
ini_set('max_execution_time', 60);

class ronda
{
	var $user_agent = 'Ronda - http://code.google.com/p/ronda';
	var $cookie = "cookies.txt";
	var $projects = array(
		'id.wikipedia',
		'jv.wikipedia',
		'su.wikipedia',
		'map-bms.wikipedia',
		'ace.wikipedia',
		'bjn.wikipedia',
		'id.wiktionary',
		'id.wikibooks',
		'id.wikiquote',
		'id.wikisource',
	);
	var $project;
	var $title;
	var $mod;
	var $user;
	var $pass;
	var $mods = array(
		'rc'   => array('title' => 'Perubahan terbaru',),
		'pr'   => array('title' => 'Suntingan tunda',),
		'dr'   => array('title' => 'Permintaan hapus',),
		'page' => array('title' => 'Statistik laman',),
		'user' => array('title' => 'Statistik pengguna',),
		'stat_rc' => array('title' => 'Statistik perubahan terbaru',),
	);
	var $page_url;
	var $idx_url;
	var $api_url;
	var $default_limit = 500;
	var $default_ns = '0|1|2|4|5|6|7|8|9|10|11|12|13|14|15|100|101';
	var $max_limit = 500;
	var $min_limit = 1;
	var $search; // html for search
	var $menu; // html for menu
	var $data;
	var $anon_only = false;
	var $diff_only = true;
	var $auto_refresh = false;
	var $refresh_time = 60000; // time to refresh in miliseconds
	var $namespaces = array(
		0 => 'Artikel',
		2 => 'Pengguna',
		4 => 'Proyek',
		6 => 'Berkas',
		8 => 'MediaWiki',
		10 => 'Templat',
		12 => 'Bantuan',
		14 => 'Kategori',
		100 => 'Portal',
		1 => 'Pembicaraan Artikel',
		3 => 'Pembicaraan Pengguna',
		5 => 'Pembicaraan Proyek',
		7 => 'Pembicaraan Berkas',
		9 => 'Pembicaraan MediaWiki',
		11 => 'Pembicaraan Templat',
		13 => 'Pembicaraan Bantuan',
		15 => 'Pembicaraan Kategori',
		101 => 'Pembicaraan Portal',
	);
	var $onload = '';
	var $jquery = '';

	/**
	 * Process a function
	 */
	function process($get)
	{
		// project
		if (in_array($get['p'], $this->projects))
			$this->project = $get['p'];
		else
			$this->project = $this->projects[0];
		// domain
		$domain = sprintf('http://%1$s.org', $this->project);
		$this->page_url = $domain . '/wiki';
		$this->idx_url = $domain . '/w/index.php';
		$this->api_url = $domain . '/w/api.php';
		// module
		$this->mod = array_key_exists($get['mod'], $this->mods) ?
			$get['mod'] : 'rc';
		$this->title = $this->mods[$this->mod]['title'];
		// menu: only display if has title -> can hide
		foreach ($this->mods as $key => $val)
		{
			if ($this->project != 'id.wikipedia' && !in_array($key, array('rc', 'stat_rc'))) continue;
			if ($val['title'])
			{
				$menu .= $menu ? ' | ' : '';
				$menu .= sprintf('<a href="./?mod=%2$s">%1$s</a>',
					$val['title'], $key);
			}
		}
		// project selection
		foreach ($this->projects as $project)
		{
			$select .= sprintf('<option value="%1$s"%2$s>%1$s</option>',
				$project, $project == $this->project ? ' selected' : '');
		}
		// page header
		$this->menu .= '<form id="project" method="get" action="./?">';
		$this->menu .= '<table cellpadding="0" cellspacing="0" width="100%">';
		$this->menu .= '<tr>';
		$this->menu .= '<td>';
		$this->menu .= $menu;
		$this->menu .= '</td>';
		$this->menu .= '<td align="right">';
		$this->menu .= '<select name="p" onchange="this.form.submit();">';
		$this->menu .= $select;
		$this->menu .= '</select>';
		$this->menu .= '<input type="hidden" name="mod" value="' . $this->mod . '" />';
		$this->menu .= '</td>';
		$this->menu .= '</tr>';
		$this->menu .= '</table>';
		$this->menu .= '</form>';
		// login
		try {
			$token = $this->login($this->user, $this->pass);
			$this->login($this->user, $this->pass, $token);
			//echo ("SUCCESS");
			$this->max_limit = 1000;
		} catch (Exception $e) {
			die("FAILED: " . $e->getMessage());
		}

		// process
		$function = 'process_' . $this->mod;
		$this->$function($get);
	}

	/**
	 */
	function process_rc($get)
	{
		// exclude user, type, only anon, only diff
		$rc_exclude_user = trim($get['exclude_user']);
		$rc_type = $get['new'] ? 'new' : 'new|edit';
		$rc_anon = $get['anon'] ? '|anon' : '';
		if ($get['anon'] != '') $this->anon_only = $get['anon'];
		if ($get['diff'] != '') $this->diff_only = $get['anon'];
		if ($get['ar'] != '') $this->auto_refresh = $get['ar'];
		// limit
		$rc_limit = intval($get['limit']);
		if (!$rc_limit) $rc_limit = $this->default_limit;
		if ($rc_limit > $this->max_limit) $rc_limit = $this->max_limit;
		if ($rc_limit < $this->min_limit) $rc_limit = $this->min_limit;

		$ns = $get['ns'];
		if (is_array($ns))
		{
			foreach ($ns as $ni)
			{
				$ni = trim($ni);
				if (array_key_exists($ni, $this->namespaces))
				{
					$rc_ns .= ($rc_ns != '' ? '|' : '') . $ni;
				}
			}
		}
		if ($rc_ns == '')
		{
			$rc_ns = $this->default_ns;
			$get['ns_select'] = 1;
		}
		$rc_ns_array = explode('|', $rc_ns);

		$ns_select = array(
			1 => 'Bawaan',
			2 => 'Semua',
			3 => 'Hanya artikel',
			4 => 'Tanpa pembicaraan',
			5 => 'Hanya pembicaraan',
		);

		// search form
		$search .= '<form id="search" name="search" method="get" action="./">';
		$search .= sprintf('Jumlah entri: <input type="text" name="limit" ' .
			'size="5" value="%1$s" /> ', $rc_limit);
		$search .= sprintf('Kecualikan pengguna: <input type="text" ' .
			'name="exclude_user" size="15" value="%1$s" /> ', $rc_exclude_user);
		$search .= '<br />Pilihan: ';
		$search .= sprintf('<input type="checkbox" name="new" value="1" ' .
			'%1$s/>Hanya baru ',
			$rc_type == 'new' ? 'checked="checked" ' : '');
		$search .= sprintf('<input type="checkbox" name="anon" value="1" ' .
			'%1$s/>Hanya anon ',
			$rc_anon ? 'checked="checked" ' : '');
		$search .= sprintf('<input type="checkbox" name="diff" value="1" ' .
			'%1$s/>Hanya perbedaan ',
			$this->diff_only ? 'checked="checked" ' : '');
		$search .= sprintf('<input type="checkbox" name="ar" value="1" ' .
			'%1$s/>Muat ulang setiap 1 menit',
			$this->auto_refresh ? 'checked="checked" ' : '');
		$search .= '<br />Ruang nama: ';
		$search .= '<select name="ns_select" id="ns_select" ' .
			'onChange="select_ns(this.form)">';
		$search .= '<option value=""></option>';
		foreach ($ns_select as $key => $val)
		{
			$search .= sprintf('<option value="%1$s"%3$s>%2$s</option>',
				$key, $val,
				$get['ns_select'] == $key ? 'selected' : ''
			);
		}
		$search .= '</select>';
		$search .= '<table class="search"><tr>';
		foreach ($this->namespaces as $key => $val)
		{
			if ($key == 1) $search .= '</tr><tr>';
			$val = str_replace('Pembicaraan ', 'P.', $val);
			$checked = in_array(intval($key), $rc_ns_array) ?
				'checked="checked" ' : '';
			$search .= sprintf('<td class="ns-%1$s"><input type="checkbox" name="ns[]" ' .
				'value="%1$s" %3$s/>%2$s</td>', $key, $val, $checked);

		}
		$search .= '</tr></table>';
		$search .= sprintf('<input type="hidden" name="p" value="%1$s" />',
			$this->project);
		$search .= '<input type="submit" value="Cari perubahan" />';
		$search .= '</form>';
		$this->search = $search;

		// curl
		$params = array(
			'action'      => 'query',
			'list'        => 'recentchanges',
			'rctype'      => $rc_type,
			'rclimit'     => $rc_limit,
			'rcnamespace' => $rc_ns,
			'rcprop'      => 'title|timestamp|user|ids|flags|sizes|parsedcomment|redirect',
			'rcshow'      => '!bot' . $rc_anon,
		);
		if ($rc_exclude_user)
		{
			$params['rcexcludeuser'] = urlencode($rc_exclude_user);
		}
		$this->data = $this->curl($params);
		if ($this->auto_refresh)
			$this->onload = ' onload="setTimeout(\'document.getElementById' .
				'(\\\'search\\\').submit()\', ' . $this->refresh_time . ')"';
	}

	/**
	 * http://id.wikipedia.org/wiki/Istimewa:Halaman_tertinjau_usang
	 * http://id.wikipedia.org/wiki/Istimewa:Statistik_validasi
	 */
	function process_pr($get)
	{
		$params = array(
			'action'      => 'query',
			'list'        => 'oldreviewedpages',
			'ordir'       => 'older',
			'ornamespace' => '0|6|10',
			'orlimit'     => $this->default_limit,
		);
		$this->data = $this->curl($params);
		if (is_array($pages = $this->data['query']['oldreviewedpages'])) {
			foreach ($pages as $key => $val) {
				$ids .= ($ids ? '|' : '') . $val['pageid'];
			}
			$revs = $this->get_pages_rev($ids);
			foreach ($this->data['query']['oldreviewedpages'] as $key => $val) {
				$last_rev = $revs['query']['pages'][$val['pageid']]['revisions'][0];
				$this->data['query']['oldreviewedpages'][$key]['lastrevid'] = $last_rev['revid'];
				$this->data['query']['oldreviewedpages'][$key]['lastuser'] = $last_rev['user'];
			}
		}
	}

	/**
	 * http://id.wikipedia.org/wiki/Kategori:Artikel yang layak untuk dihapus
	 */
	function process_dr($get)
	{
		$params = array(
			'action'      => 'query',
			'list'        => 'categorymembers',
			'cmtitle'     => urlencode('Kategori:Artikel yang layak untuk dihapus'),
			'cmprop'      => 'ids|title|type|timestamp',
			'cmsort'      => 'timestamp',
			'cmdir'       => 'desc',
			'cmlimit'     => $this->default_limit,
		);
		$this->data = $this->curl($params);
		if (is_array($pages = $this->data['query']['categorymembers'])) {
			foreach ($pages as $key => $val) {
				$ids .= ($ids ? '|' : '') . $val['pageid'];
			}
			$revs = $this->get_pages_rev($ids);
			foreach ($this->data['query']['categorymembers'] as $key => $val) {
				$last_rev = $revs['query']['pages'][$val['pageid']]['revisions'][0];
				$this->data['query']['categorymembers'][$key]['lastrevid'] = $last_rev['revid'];
				$this->data['query']['categorymembers'][$key]['lastuser'] = $last_rev['user'];
				// $first_raw = $this->get_pages_rev($val['pageid'], true);
				// $this->data['query']['categorymembers'][$key]['firstuser'] = $first_raw['query']['pages'][$val['pageid']]['revisions'][0]['user'];
			}
		}
	}

	/**
	 *
	 */
	function process_page($get)
	{
		$page = $get['page'];
		if ($page == '') $page = 'Halaman Utama';

		// search form
		$search .= '<form id="search" name="search" method="get" action="./">';
		$search .= sprintf('<input type="hidden" name="mod" value="%1$s" />',
			$this->mod);
		$search .= sprintf('<input type="hidden" name="p" value="%1$s" />',
			$this->project);
		$search .= sprintf('Judul: <input type="text" ' .
			'name="page" size="30" value="%1$s" /> ', $page);
		$search .= '<input type="submit" value="Cari" />';
		$search .= '</form>';
		$this->search = $search;

		// basic info
		$params = array(
			'action'      => 'query',
			'prop'        => 'info|flagged',
			'titles'      => urlencode($page),
		);
		$raw = $this->curl($params);
		$parts = array('title', 'pageid', 'ns', 'touched', 'lastrevid', 'length');
		if ($tmp = $raw['query']['pages'])
		{
			$key = key($tmp); // get the first element
			$tmp = $tmp[$key];
			if ($key > 0)
				foreach ($parts as $key => $part)
					$this->data['page'][$part] = $tmp[$part];
			else
				return;
			// flagged?
			if ($tmp['flagged'])
			{
				$this->data['page']['revlevel'] = $tmp['flagged']['level_text'];
				$this->data['page']['stablerevid'] = $tmp['flagged']['stable_revid'];
				$this->data['page']['pendingsince'] = $tmp['flagged']['pending_since'];
			}
		}
		// parse
		$params = array(
			'action'      => 'parse',
			'page'        => urlencode($page),
			'prop'        => 'revid|displaytitle|sections|categories|images|templates|links|langlinks|iwlinks|externallinks',
		);
		$raw = $this->curl($params);
		$parts = array('sections', 'categories',
			'images', 'templates', 'links', 'langlinks', 'iwlinks',
			'externallinks');
		if ($tmp = $raw['parse'])
			foreach ($parts as $part)
				$this->data['page'][$part] = $tmp[$part];
		// revisions
		$params = array(
			'action'      => 'query',
			'prop'        => 'revisions',
			'titles'      => urlencode($page),
			'rvprop'      => 'ids|timestamp|flags|parsedcomment|user|size',
			'rvlimit'     => 500,
		);
		$raw = $this->curl($params);
		if ($tmp = $raw['query']['pages'])
		{
			$key = key($tmp); // get the first element
			$tmp = $tmp[$key];
			if ($key > 0)
				$this->data['page']['revisions'] = $tmp['revisions'];
		}
		// backlinks
		$params = array(
			'action'     => 'query',
			'list'       => 'backlinks',
			'bllimit'    => 500,
			'bltitle'    => urlencode($page),
		);
		$raw = $this->curl($params);
		if ($raw['query']['backlinks'])
			$this->data['page']['backlinks'] = $raw['query']['backlinks'];
		else
			$this->data['page']['backlinks'] = 0;
		// finalization
		if (is_array($this->data['page']['links']))
			$this->data['page']['links']
				= $this->subval_sort($this->data['page']['links'], '*');
		if (is_array($this->data['page']['backlinks']))
			$this->data['page']['backlinks']
				= $this->subval_sort($this->data['page']['backlinks'], 'title');
		if (is_array($this->data['page']['categories']))
			foreach ($this->data['page']['categories'] as $key => $val)
				$this->data['page']['categories'][$key]['*']
					= str_replace('_', ' ', $val['*']);
		if (is_array($this->data['page']['images']))
			foreach ($this->data['page']['images'] as $key => $val)
				$this->data['page']['images'][$key]
					= str_replace('_', ' ', $val);
		$this->title = $this->title . ': ' . $page;
	}

	/**
	 *
	 */
	function process_user($get)
	{
		$user = $get['user'];

		// search form
		$search .= '<form id="search" name="search" method="get" action="./">';
		$search .= sprintf('<input type="hidden" name="mod" value="%1$s" />',
			$this->mod);
		$search .= sprintf('<input type="hidden" name="p" value="%1$s" />',
			$this->project);
		$search .= sprintf('Judul: <input type="text" ' .
			'name="user" size="30" value="%1$s" /> ', $user);
		$search .= '<input type="submit" value="Cari" />';
		$search .= '</form>';
		$this->search = $search;

		// basic info
		$params = array(
			'action'      => 'query',
			'list'        => 'allusers',
			'aulimit'     => 1,
			'auprop'      => 'blockinfo|groups|editcount|registration',
			'aufrom'      => urlencode($user),
		);
		$raw = $this->curl($params);

		$parts = array('name', 'userid', 'editcount', 'registration', 'groups');
		if ($tmp = $raw['query']['allusers'][0])
		{
			if ($tmp['name'] != $user) return;
			$this->data['user'] = $tmp;
		}
		if ($this->data['user']['groups'])
			$this->data['user']['groups'] = implode(', ', $this->data['user']['groups']);
		// finalization
		$this->title = $this->title . ': ' . $page;
	}

	/**
	 * 2012-05-30 10:37
	 * type, ns, title, rcid, pageid, revid, old_revid, user, timestamp, anon
	 */
	function process_stat_rc() {
		// Call API
		$params = array(
			'action'      => 'query',
			'list'        => 'recentchanges',
			'rclimit'     => $this->max_limit,
			'rctype'      => 'new|edit',
			'rcprop'      => 'title|timestamp|user|ids|redirect|flags',
			'rcshow'      => '!bot',
		);
		$raw = $this->curl($params);
		$rc = $raw['query']['recentchanges'];
		$i = 0;
		// Push users
		foreach ($rc as $k => $val) {
			$time = strtotime($val['timestamp']);
			if ($i == 0)
				$this->data['end'] = $time;
			$this->data['start'] = $time;
			$i++;
			$type = isset($val['anon']) ? 'anons' : 'users';
			$users = &$this->data[$type][$val['user']];
			if (!isset($users)) {
				$users = array('total' => 0, 'edit' => 0, 'new' => 0, 'page' => 0);
			}
			$users['pages'][$val['pageid']]++;
			$users['total']++;
			$users[$val['type']]++;
		}
		$this->data['count'] = $i;
		// Sort
			$this->array_sort_by_column($this->data['users'], 'total', SORT_DESC);
		if (is_array($this->data['anons']))
			ksort($this->data['anons']);

		// Iterate
		$types = array('users', 'anons');
		foreach ($types as $type) {
			if (is_array($this->data[$type])) {
				$rows = &$this->data[$type];
				foreach ($rows as $user => $row) {
					$rows[$user]['page'] = count($rows[$user]['pages']);
					$rows[$user]['epp'] = $rows[$user]['total'] / $rows[$user]['page'];
				}
			}
		}
	}

	/**
	 * Display HTML
	 */
	function html()
	{
		// title
		$this->title = sprintf('Ronda: %1$s (%2$s)',
			$this->title, $this->project);
		// process
		$function = 'html_' . $this->mod;
		$content = $this->$function();
		$ret .= sprintf('<div id="menu">%1$s</div>', $this->menu);
		$ret .= sprintf('<h1>%1$s</h1>', $this->title);
		$ret .= $this->search;
		$ret .= $content;
		return($ret);
	}

	/**
	 */
	function html_rc()
	{
		$raws = $this->data;
		if ($raws)
		{
			$rcs = array();
			if ($raws2 = $raws['query']['recentchanges'])
			{
				// get unique page id
				foreach ($raws2 as $raw)
				{
					$key = $raw['pageid'];
					if (!array_key_exists($key, $rcs))
					{
						$rcs[$key]['pageid'] = $raw['pageid'];
						$rcs[$key]['revid'] = $raw['revid'];
						$rcs[$key]['old_revid'] = $raw['old_revid'];
						if (array_key_exists('minor', $raw))
							$rcs[$key]['minor'] = $raw['minor'];
						$rcs[$key]['timestamp'] = $raw['timestamp'];
						$rcs[$key]['title'] = $raw['title'];
						$rcs[$key]['user'] = $raw['user'];
						$rcs[$key]['type'] = $raw['type'];
						$rcs[$key]['newlen'] = $raw['newlen'];
						$rcs[$key]['oldlen'] = $raw['oldlen'];
						$rcs[$key]['count'] = 1;
						$rcs[$key]['users'][$raw['user']]['count'] = 1;
					}
					else
					{
						if (array_key_exists('new', $raw))
							$rcs[$key]['type'] = 'new';
						$rcs[$key]['old_revid'] = $raw['old_revid'];
						$rcs[$key]['count']++;
						$rcs[$key]['users'][$raw['user']]['count']++;
						$rcs[$key]['oldlen'] = $raw['oldlen'];
					}
					if ($this->check_revert($raw['parsedcomment']))
						$rcs[$key]['revert'] = true;
					if (array_key_exists('redirect', $raw))
						$rcs[$key]['redirect'] = true;
					$rcs[$key]['changes'][] = $raw;
					$rcs[$key]['ns'] = $raw['ns'];
					if (array_key_exists('anon', $raw))
					{
						$rcs[$key]['anon'] = 'yes';
						$rcs[$key]['users'][$raw['user']]['anon'] = true;
					}
				}
				// write
				$trusted = $this->trusted_users();
				$ret .= '<table class="data">';
				foreach ($rcs as $rci)
				{
					$rc = $rci;
					$users = '';
					$time = strtotime($rc['timestamp']);
					$rc['difflen'] = $rc['newlen'] - $rc['oldlen'];
					$i = 0;
					$ucount = count($rc['users']);
					foreach ($rc['users'] as $user_id => $user)
					{
						$url = '%1$s/Special:Contributions/%2$s';
						$url = sprintf($url, $this->page_url, $user_id);
						$class = $user['anon'] ? 'user-anon' : 'user-login';
						if (is_array($trusted))
							if (!$user['anon'] && in_array($user_id, $trusted))
								$class = 'user-trusted';
						$users .= sprintf('<a href="%2$s" class="%3$s">%1$s</a>',
							$user_id, $url, $class);
						$users .= $user['count'] > 1 ?
							' (' . $user['count'] . 'x)' : '';
						$i++;
						$users .= sprintf('&nbsp;<a href="./?mod=user&project=%1$s' .
							'&user=%2$s" class="stat">&raquo;</a> ',
							$this->project, $user_id);
					}
					$class = ($rc['anon'] == 'yes' && !$this->anon_only) ? 'anon' : '';
					$cur_date = date('d M Y', $time);
					if ($rc['type'] == 'new')
					{
						$url = sprintf('%1$s/%2$s',
							$this->page_url, $this->format_title($rc['title']));
					}
					else
					{
						$url = sprintf('%1$s?diff=%2$s&oldid=%3$s',
							$this->idx_url, $rc['revid'], $rc['old_revid']);
						if ($this->diff_only) $url .= '&diffonly=1';
					}
					// page quality url
					$url_pq = sprintf('./?mod=page&p=%2$s&page=%1$s',
						urlencode($rc['title']),
						$this->project
					);

					if ($cur_date != $last_date)
					{
						$ret .= sprintf('<tr><td colspan="5" class="date">%1$s</td></tr>', $cur_date);
					}
					$ret .= sprintf('<tr class="%1$s" valign="top">', $class);
					$ret .= sprintf('<td width="1" class="ns-%1$s">&nbsp;</td>', $rc['ns']);
					$ret .= sprintf('<td width="1">%1$s</td>', date('H.i', $time));
					$ret .= sprintf('<td><a href="%2$s" class="%4$s">%1$s</a>' .
						'%3$s <a href="%6$s" class="stat">&raquo;</a> . . %5$s</td>',
						$rc['title'], $url,
						($rc['count'] > 1 ? ' (' . $rc['count'] . 'x)' : ''),
						($rc['redirect'] ? 'redirect ' : '') .
							($rc['revert'] ? 'revert ' : '') .
							($rc['type'] == 'new' ? 'new ' : ''),
						$this->format_diff($rc['difflen']),
						$url_pq
					);
					$ret .= sprintf('<td width="1">%1$s</td>', ($rc['type'] == 'new' ? 'B' : ''));
					$ret .= sprintf('<td>%1$s</td>', $users);
					$ret .= sprintf('<td class="changes">%1$s</td>',
						$this->format_summary($rc['changes']));
					$ret .= '</tr>';

					$last_date = $cur_date;
				}
				$ret .= '</table>';
			}
		}
		return($ret);
	}

	/**
	 * http://id.wikipedia.org/w/index.php?diff=cur&oldid=4111616
	 */
	function html_pr()
	{
		$i = 0;
		$base = $this->idx_url . '?diff=cur&oldid=%1$s&diffonly=1';
		if ($rows = $this->data['query']['oldreviewedpages'])
		{
			$ret .= sprintf('<p>Total %s data.</p>', count($rows));
			$ret .= '<table class="data">';
			foreach ($rows as $row)
			{
				$i++;
				$url = sprintf($base, $row['stable_revid']);
				$time = strtotime($row['pending_since']);
				$cur_date = date('d M Y', $time);

				if ($cur_date != $last_date)
				{
					$ret .= sprintf('<tr><td colspan="3" class="date">' .
						'%1$s</td></tr>', $cur_date);
				}
				$ret .= '<tr>';
				$ret .= sprintf('<td width="1" class="ns-%1$s">%2$s</td>',
					$row['ns'], $i);
				$ret .= sprintf('<td width="1">%1$s</td>', date('H.i', $time));
				$ret .= sprintf(
					'<td><a href="%2$s" class="%4$s">%1$s</a> . . %3$s</td>',
					$row['title'], $url,
					$this->format_diff($row['diff_size']),
					$row['under_review'] ? 'revert' : ''
				);
				$ret .= sprintf('<td>%s</td>', $row['lastuser']);
				$ret .= '</tr>';
				$last_date = $cur_date;
			}
			$ret .= '</table>';
		}
		return($ret);
	}

	/**
	 */
	function html_dr()
	{
		$base = $this->page_url . '/%1$s';
		$i = 0;
		if ($rows = $this->data['query']['categorymembers'])
		{
			$ret .= sprintf('<p>Total %s data.</p>', count($rows));
			$ret .= '<table class="data">';
			foreach ($rows as $row)
			{
				$i++;
				$url = sprintf($base, $this->format_title($row['title']));
				$time = strtotime($row['timestamp']);
				$cur_date = date('d M Y', $time);

				if ($cur_date != $last_date) {
					$ret .= sprintf('<tr><td colspan="3" class="date">' .
						'%1$s</td></tr>', $cur_date);
				}
				$ret .= '<tr>';
				$ret .= sprintf('<td width="1" class="ns-%1$s">%2$s</td>',
					$row['ns'], $i);
				$ret .= sprintf('<td width="1">%1$s</td>', date('H.i', $time));
				$ret .= sprintf(
					'<td><a href="%2$s">%1$s</a></td>',
					$row['title'], $url
				);
				$ret .= sprintf('<td>%s</td>', $row['lastuser']);
				$ret .= sprintf('<td>%s</td>', $row['firstuser']);
				$ret .= '</tr>';
				$last_date = $cur_date;
			}
			$ret .= '</table>';
		}
		return($ret);
	}

	/**
	 * sections: toclevel, level, line, number, index, fromtitle, byteoffset, anchor
	 * categories: sortkey, *
	 * images: plain array
	 * templates: ns, *, exists
	 * links: ns, *, exists
	 * backlinks: pageid, ns, title
	 * langlinks: lang, url, *
	 * iwlinks: prefix, url, *
	 * externallinks: plain
	 */
	function html_page()
	{
		$page_props = array(
			'title' => array('title' => 'Judul',),
			'ns' => array('title' => 'Ruang nama',),
			'length' => array('title' => 'Panjang',),
			'touched' => array('title' => 'Revisi terakhir',),
			'lastrevid' => array('title' => 'ID revisi terakhir',),
			'stablerevid' => array('title' => 'ID revisi stabil',),
			'sections' => array('title' => 'Subbagian', 'child_field' => 'line',),
			'categories' => array('title' => 'Kategori', 'child_field' => '*',),
			'images' => array('title' => 'Berkas',),
			'templates' => array('title' => 'Templat', 'child_field' => '*',),
			'links' => array('title' => 'Pranala internal', 'child_field' => '*',),
			'backlinks' => array('title' => 'Pranala balik', 'child_field' => 'title',),
			'langlinks' => array('title' => 'Pranala antarbahasa', 'child_field' => 'lang',),
			'iwlinks' => array('title' => 'Pranala antarwiki', 'child_field' => '*',),
			'externallinks' => array('title' => 'Pranala luar',),
			'revisions' => array('title' => 'Revisi',),
		);
		//	'pageid' => array('title' => 'ID laman',),
		//	'revlevel' => array('title' => 'Status revisi',),
		//	'pendingsince' => array('title' => 'Tertunda sejak',),
		if ($rows = $this->data['page'])
		{
			$ret .= '<table>';
			foreach ($page_props as $key => $val)
			{
				$ext = ($key == 'externallinks');
				$row = $rows[$key];
				$header = $page_props[$key]['title'];
				if (!is_array($row))
				{
					if ($key == 'title')
						$row = sprintf('<a href="%2$s/%1$s">%1$s</a>',
							$row, $this->page_url);
					$content = $row;
				}
				else
				{
					if ($key == 'revisions')
					{
						$content = sprintf('%1$s', count($row));
					}
					else
					{
						if (count($row) > 0)
							$header = sprintf('%1$s (<a href="#" id="%3$s_header">%2$s</a>)', $header, count($row), $key);
						else
							$header = sprintf('%1$s (%2$s)', $header, count($row));
						$list = '';
						$i = 0;
						foreach ($row as $item)
						{
							$i++;
							// get the value
							if ($val['child_field'])
								$item_val = $item[$val['child_field']];
							else
								$item_val = $item;
							// put link
							$item_url = $this->page_url . '/';
							switch ($key)
							{
								case 'sections':
									$item_url .= $this->data['page']['title'] . '#' . str_replace(' ', '_', $item_val);
									break;
								case 'images':
									$item_url .= $this->namespaces[6] . ':' . $item_val;
									break;
								case 'categories':
									$item_url .= $this->namespaces[14] . ':' . $item_val;
									break;
								case 'langlinks':
									$item_url = $item['url'];
									break;
								case 'externallinks':
									$item_url = $item_val;
									break;
								default:
									$item_url .= $item_val;
									break;
							}

							$item_val = sprintf('<a href="%2$s">%1$s</a>', $item_val, $item_url);
							// external links special treatment
							if (!$ext)
							{
								$list .= $list ? '; ' : '';
								$list .= sprintf($i . '. %1$s', $item_val);
							}
							else
								$list .= sprintf('<li>%1$s</li>', $item_val);
						}
						$content = sprintf('<div id="%1$s_content">', $key);
						$content .= $ext ? '<ol>' : '';
						$content .= $list;
						$content .= $ext ? '</ol>' : '';
						$content .= '</div>';
						// jquery
						$this->jquery .= sprintf('$(\'a#%1$s_header\').click(function(){', $key);
						$this->jquery .= sprintf('$(\'div#%1$s_content\').toggle();', $key);
						$this->jquery .= "});";
					}
				}
				$ret .= sprintf(
					'<tr valign="top"><td class="label">%1$s:</td>' .
					'<td class="content">%2$s</td></tr>',
					$header, $content);
			}
			$ret .= '</table>';
		}
		return($ret);
	}

	/**
	 */
	function html_user()
	{
		$page_props = array(
			'name' => array('title' => 'Nama pengguna',),
			'userid' => array('title' => 'ID pengguna',),
			'registration' => array('title' => 'Tanggal pendaftaran',),
			'groups' => array('title' => 'Kelompok',),
			'editcount' => array('title' => 'Jumlah suntingan',),
		);
		if ($rows = $this->data['user'])
		{
			$ret .= '<table>';
			foreach ($page_props as $key => $val)
			{
				$row = $rows[$key];
				$ret .= sprintf('<tr valign="top"><td>%1$s</td><td>%2$s</td></tr>',
					$page_props[$key]['title'],
					is_array($row) ? count($row) : $row
				);
			}
			$ret .= '</table>';
		}
		return($ret);
	}

	/**
	 */
	function html_stat_rc() {
		$types = array('users', 'anons');
		foreach ($types as $type) {
			if ($rows = $this->data[$type]) {
				foreach ($rows as $user => $row) {
					$url = '%1$s/Special:Contributions/%2$s';
					$url = sprintf($url, $this->page_url, $user);
					$row['new'] = $row['new'] ? $row['new'] : '-';
					$row['edit'] = $row['edit'] ? $row['edit'] : '-';
					$content[$type] .= '<tr align="right">';
					$content[$type] .= sprintf('<td align="left"><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
						$url, $user, $row['new'], $row['edit'], $row['total'], $row['page'], number_format($row['epp'], 1)
					);
					$content[$type] .= '</tr>';
				}
			}
			if ($content[$type] != '') {
				$header = '<tr><th>&nbsp;</th><th>Baru</th><th>Edit</th><th>Total</th><th>Hlm</th><th>/Hlm</th></tr>';
				$content[$type] = '<table class="table">' . $header . $content[$type] . '</table>';
			} else {
				$content[$type] = '<br />Data tidak tersedia.';
			}
		}
		$ret .= sprintf('<p>Dari %s hingga %s (WIB) berdasarkan %s data terakhir.</p>',
			date('d M Y H.i.s', $this->data['start']),
			date('d M Y H.i.s', $this->data['end']),
			$this->data['count']
		);
		$ret .= '<table>';
		$ret .= '<tr valign="top">';
		$ret .= '<td><strong>Pengguna:</strong>' . $content['users'] . '</td>';
		$ret .= '<td>&nbsp;</td>';
		$ret .= '<td><strong>Anonim:</strong>' . $content['anons'] . '</td>';
		$ret .= '</tr>';
		$ret .= '</table>';
		return($ret);
	}

	/**
	 */
	function curl($params, $format = 'json', $post = '')
	{
		foreach ($params as $key => $val)
		{
			if ($val == '') continue;
			$param .= sprintf('&%1$s=%2$s', $key, $val);
		}
		$url = $this->api_url . '?format=' . $format . $param;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_ENCODING, "UTF-8" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
		if (!empty($post)) curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$ret = curl_exec($ch);
		if (!$ret) {
			throw new Exception("Gagal mengambil data dari server ($url): " . curl_error($ch));
		}
		if ($format == 'json') $ret = json_decode($ret, true);
		curl_close($ch);
		return($ret);
	}

	/**
	 */
	function login($user, $pass, $token = '') {
		$post = "action=login&lgname=$user&lgpassword=$pass";
		$params = array(
			'action'      => 'login',
		);
		if (!empty($token)) {
			$post .= '&lgtoken=' . $token;
		}
		$data = $this->curl($params, 'xml', $post);
		if (empty($data)) {
			// throw new Exception("No data received from server. Check that API is enabled.");
		}
		$xml = simplexml_load_string($data);
		if (!empty($token)) {
			$expr = "/api/login[@result='Success']";
			$result = $xml->xpath($expr);
			if(!count($result)) {
				// throw new Exception("Login failed");
			}
		} else {
			$expr = "/api/login[@token]";
			$result = $xml->xpath($expr);
			if(!count($result)) {
				// throw new Exception("Login token not found in XML");
			}
		}
		if ($result)
			return($result[0]->attributes()->token);
	}

	/**
	 */
	function trusted_users()
	{
		$params = array(
			'action'  => 'query',
			'list'    => 'allusers',
			'aulimit' => $this->max_limit,
			'auprop'  => 'blockinfo|editcount|registration',
		);
		$params['augroup'] = 'editor';
		$raw1 = $this->curl($params);
		$this->get_users($raw1, $raw);
		$params['augroup'] = 'sysop';
		$raw2 = $this->curl($params);
		$this->get_users($raw2, $raw);
		if (is_array($raw)) $raw = array_unique($raw);
		return($raw);
	}

	/**
	 */
	function get_users($raw, &$users)
	{
		if ($tmp = $raw['query']['allusers'])
			foreach ($tmp as $user)
				$users[] = $user['name'];
	}

	/**
	 */
	function get_pages_rev($ids, $first = false) {
		$params = array(
			'action'      => 'query',
			'prop'        => 'revisions',
			'pageids'     => $ids,
			'rvprop'      => 'ids|timestamp|user|size',
		);
		if ($first) {
			$params['rvdir'] = 'newer';
			$params['rvlimit'] = 1;
		}
		$raw = $this->curl($params);
		return($raw);
	}

	function format_title($title)
	{
		$ret = urlencode(str_replace(' ', '_', $title));
		return($ret);
	}

	/**
	 */
	function format_date($date, $format = 'datetime')
	{
	}

	/**
	 */
	function format_summary($changes)
	{
		$max = 50;
		$ret = count($changes) == 1 ?
			strip_tags($changes[0]['parsedcomment']) : '';
		if (strlen($ret) > $max) $ret = substr($ret, 0, $max) . ' ...';
		return($ret);
	}

	/**
	 */
	function format_diff($diff)
	{
		$num = (($diff > 0) ? ('+' . $diff) : $diff);
		$size = ($diff == 0 ?
			'size-null' : ($diff > 0 ? 'size-pos' : 'size-neg'));
		$large = (abs(intval($diff)) >= 500 ? ' size-large' : '');
		$ret = sprintf('<span class="%2$s%3$s">(%1$s)</span>',
			$num, $size, $large);
		return($ret);
	}

	/**
	 */
	function check_revert($summary)
	{
		$found = false;
		$phrases = array(
			'dikembalikan ke versi terakhir',
			'mengembalikan revisi',
			'Membatalkan revisi',
		);
		foreach ($phrases as $phrase)
			if (strpos($summary, $phrase) !== false) $found = true;
		return($found);
	}

	/**
	 * http://www.firsttube.com/read/sorting-a-multi-dimensional-array-with-php/
	 */
	function subval_sort($a, $subkey) {
		foreach($a as $k=>$v) {
			$b[$k] = strtolower($v[$subkey]);
		}
		asort($b);
		foreach($b as $key => $val) {
			$c[] = $a[$key];
		}
		return $c;
	}

	/**
	 * http://stackoverflow.com/questions/2699086/php-sort-multidimensional-array-by-value
	 */
	function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
		$sort_col = array();
		foreach ($arr as $key=> $row) {
			$sort_col[$key] = $row[$col];
		}
		array_multisort($sort_col, $dir, $arr);
	}

}