<?php 
/* $Id:$ */

//used to get module information
function customcontexts_getmodulevalue($id) {
	global $db;
	$sql = "select value from customcontexts_module where id = '$id'";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	return isset($results)?$results[0][0]:null;
}

//used to get module information
function customcontexts_setmodulevalue($id,$value) {
	global $db;
	$sql = "update customcontexts_module set value = '$value' where id = '$id'";
	$db->query($sql);
}

//after dialplan is created and ready for injection, we grab the includes of any context the user added in admin
function customcontexts_hookGet_config($engine) {
	global $db;
	global $ext;
	switch($engine) {
		case 'asterisk':
			$sql = "update customcontexts_includes_list set missing = 1 where context not in (select context from customcontexts_contexts_list where locked = 1)";
			$db->query($sql);
			$sql = "select context from customcontexts_contexts_list";
			$results = $db->getAll($sql);
			if(DB::IsError($results)) {
 				$results = null;
			}
			foreach ($results as $val) {
				$section = $val[0];
				if (isset($ext->_includes[$section])) {
            		$i = 0;
					foreach ($ext->_includes[$section] as $include) {
						if ($section == 'outbound-allroutes') {
							$i = $i + 1;
							$sql = "update customcontexts_includes_list  set missing = 0, sort = $i where context = '$section' and include = '$include'";
							$db->query($sql);
//fix prioritized contexts       , description = '$include'
							$sql = "update customcontexts_includes_list  set include = '$include', description = '$include', missing = 0, sort = $i where missing = 1 and context = '$section' and substring(include,1,6) = 'outrt-' and substring(include,10) = substring('$include',10)";
							$db->query($sql);
//fix allowed prioritized contexts  (i did not do , sort = $i, maybe i should)    context = '$section' and
							$sql = "update customcontexts_includes set include = '$include' where substr(include,1,6) = 'outrt-' and substr(include,10) = substr('$include',10)";
							$db->query($sql);
						} else {
							$sql = "update customcontexts_includes_list  set missing = 0, sort = $i where context = '$section' and include = '$include'";
							$db->query($sql);
                        }
						$sql = "INSERT INTO customcontexts_includes_list (context, include, description, sort) VALUES ('$section', '$include', '$include', $i)";
						$db->query($sql);
					}
				}
			}
			$sql = "delete from  customcontexts_includes_list where missing = 1";
			$db->query($sql);
		break;
	}
}

// provide hook for routing
function customcontexts_hook_core($viewing_itemid, $target_menuid) {
	switch ($target_menuid) {
		// only provide display for outbound routing
		case 'routing':
/*
			$route = substr($viewing_itemid,4);
			$hookhtml = '';
            return $hookhtml;
*/
			return '';
		break;
		default:
				return false;
		break;
	}
}

//this is to catch any rename reorder or delete route, so i can fix custom contexts
function customcontexts_hookProcess_core($viewing_itemid, $request) {
	switch ($request['display']) {
        case 'routing':
			if(isset($request['Submit'])) {
//				$route = substr($viewing_itemid,4);
//				$priority = (int)(substr($viewing_itemid,0,3));
			} 
			switch ($request['action']) {
				case 'delroute':
//					$route = substr($viewing_itemid,4);
					$priority = (int)(substr($viewing_itemid,0,3));
					customcontexts_routing_prioritize($request['action'],$priority);
				break;
				case 'prioritizeroute':
					$fullroute = $viewing_itemid;
					if (isset($request['reporoutekey'])) {
              			$outbound_routes = core_routing_getroutenames();
						$fullroute = $outbound_routes[(int)$request['reporoutekey']][0];
	           		}
//					$route = substr($fullroute,4);
					$priority = (int)(substr($fullroute,0,3));
					$direction = $request['reporoutedirection'];
					customcontexts_routing_prioritize($request['action'],$priority,$direction);
				break;
				case 'renameroute';
					$newname = $request['newroutename'];
					$route = $viewing_itemid;
					$priority = (substr($viewing_itemid,0,3));
                    $fullnewname = 'outrt-'.$priority.'-'.$newname;
                    $fullroutename = 'outrt-'.$route;
					customcontexts_routing_editname($fullroutename,$fullnewname);
				break;
				default:
		
				break;
			}
		break;
	}
}

function customcontexts_routing_editname($route,$newname) {
	global $db;
//fix renamed contexts       , description = '$include'
	$sql = "update customcontexts_includes_list  set include = '$newname', description = '$newname', missing = 0 where context = 'outbound-allroutes' and include = '$route'";
	$db->query($sql);
//fix allowed renamed contexts  (i did not do , sort = $i, maybe i should)   context = 'outbound-allroutes' and
	$sql = "update customcontexts_includes set include = '$newname' where include = '$route'";
	$db->query($sql);
}    

function customcontexts_routing_prioritize($action,$priority,$direction=null) {
    global $db;
	$outbound_routes = core_routing_getroutenames();
	foreach ($outbound_routes as $route) {
        $routename = $route[0];
        $routepriority = (int)(substr($routename,0,3));
        switch ($action) {
            case 'prioritizeroute':
				$addpriority = ($direction=='up')?-1:1;
            	if ($priority + $addpriority == $routepriority) {
					$newpriority = str_pad($priority, 3, "0", STR_PAD_LEFT);
                    $newroute = 'outrt-'.$newpriority.'-'.substr($routename,4);
				} elseif ($priority == $routepriority) {
					$newpriority = str_pad($priority + $addpriority, 3, "0", STR_PAD_LEFT);
                    $newroute = 'outrt-'.$newpriority.'-'.substr($routename,4);
				}
				if (isset($newroute)) {
//fix prioritized contexts    , description = '$newroute'
					$sql = "update customcontexts_includes_list  set include = '$newroute', description = '$newroute', missing = 0, sort = $newpriority where context = 'outbound-allroutes' and include= 'outrt-$routename'";
//echo $sql;
					$db->query($sql);
//fix allowed prioritized contexts  (i did not do , sort = $i, maybe i should)    context = 'outbound-allroutes' and
					$sql = "update customcontexts_includes set include = '$newroute' where include = 'outrt-$routename'";
//echo $sql;
					$db->query($sql);
				}
				unset($newroute);
            break;
            case 'delroute';
            	if ($routepriority > $priority) {
					$newpriority = str_pad($routepriority - 1, 3, "0", STR_PAD_LEFT);
                    $newroute = 'outrt-'.$newpriority.'-'.substr($routename,4);
//fix prioritized contexts   , description = '$newroute'
					$sql = "update customcontexts_includes_list  set include = '$newroute', description = '$newroute', missing = 0, sort = $newpriority where context = 'outbound-allroutes' and include= 'outrt-$routename'";
//echo $sql;
					$db->query($sql);
//fix allowed prioritized contexts  (i did not do , sort = $i, maybe i should)   context = 'outbound-allroutes' and
					$sql = "update customcontexts_includes set include = '$newroute' where include = 'outrt-$routename'";
//echo $sql;
					$db->query($sql);
				}
				unset($newroute);
            break;
		}            
	}
}


//this lists all includes from the sql database (for the requsted context) which we parsed out of the dialplan
//from any contexts the user entered in admin - information was saved to database on the last reload

function customcontexts_getincludeslist($context) {
	global $db;
//	$sql = "select include, description from customcontexts_includes_list where context = '".$context."' order by description";
	$sql = "select include, customcontexts_includes_list.description, count(customcontexts_contexts_list.context) as preemptcount  from customcontexts_includes_list left outer join customcontexts_contexts_list on include = customcontexts_contexts_list.context where customcontexts_includes_list.context = '$context' group by include, customcontexts_includes_list.description order by customcontexts_includes_list.description";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	foreach ($results as $val)
		$tmparray[] = array($val[0], $val[1], $val[2]);
	return $tmparray;
}

//lists any contexts the user entered in admin for us to parse for includes to make available to his custom contexts
function customcontexts_getcontextslist() {
	global $db;
	$sql = "select context, description from customcontexts_contexts_list order by description";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	foreach ($results as $val)
		$tmparray[] = array($val[0], $val[1]);
	return $tmparray;
}

//these are the users selections of includes for the selected custom context
function customcontexts_getincludes($context) {
	global $db;
//	$sql = "select customcontexts_contexts_list.context, customcontexts_contexts_list.description as contextdescription, customcontexts_includes_list.include, customcontexts_includes_list.description, if(saved.include is null, 'no', if(saved.timegroupid is null, 'yes', saved.timegroupid)) as allow, saved.sort from customcontexts_contexts_list inner join customcontexts_includes_list on customcontexts_contexts_list.context = customcontexts_includes_list.context left outer join (select * from customcontexts_includes where context = '$context') saved on customcontexts_includes_list.include = saved.include order by customcontexts_contexts_list.description, customcontexts_includes_list.description";
	$sql = "select customcontexts_contexts_list.context, customcontexts_contexts_list.description as contextdescription, customcontexts_includes_list.include, customcontexts_includes_list.description, if(saved.include is null, 'no', if(saved.timegroupid is null, if(saved.userules is null, 'yes', saved.userules), saved.timegroupid)) as allow, if(saved.sort is null,customcontexts_includes_list.sort,saved.sort) as sort, count(preemptcheck.context) as preemptcount from customcontexts_contexts_list inner join customcontexts_includes_list on customcontexts_contexts_list.context = customcontexts_includes_list.context left outer join (select * from customcontexts_includes where context = '$context           ') saved on customcontexts_includes_list.include = saved.include left outer join customcontexts_contexts_list  preemptcheck on customcontexts_includes_list.include = preemptcheck.context group by customcontexts_contexts_list.context, customcontexts_contexts_list.description, customcontexts_includes_list.include, customcontexts_includes_list.description, if(saved.include is null, 'no', if(saved.timegroupid is null, 'yes', saved.timegroupid)), saved.sort order by customcontexts_contexts_list.description, if(saved.sort is null,101,saved.sort), customcontexts_includes_list.description;";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	foreach ($results as $val)
		$tmparray[] = array($val[0], $val[1], $val[2], $val[3], $val[4], $val[5], $val[6]);
//		0-context   	 1-contextdescription   	 2-include   	 3-description   	 4-allow   	 5-sort	6-preemptcount
	return $tmparray;
}

//these are the users custom contexts
function customcontexts_getcontexts() {
	global $db;
	$sql = "select context, description from customcontexts_contexts order by description";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	foreach ($results as $val)
		$tmparray[] = array($val[0], $val[1]);
	return $tmparray;
}

//lists any time groups defined by the user
function customcontexts_gettimegroups() {
	global $db;
	$sql = "select id, description from customcontexts_timegroups order by description";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	foreach ($results as $val)
		$tmparray[] = array($val[0], $val[1]);
	return $tmparray;
}

//these are the users time selections for the current timegroup
function customcontexts_gettimes($timegroup) {
	global $db;
	$sql = "select id, time from customcontexts_timegroups_detail where timegroupid = $timegroup";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	foreach ($results as $val)
		$tmparray[] = array($val[0], $val[1]);
	return $tmparray;
}

//allow reload to get our config
//we add all user custom contexts ad include his selected includes
//also maybe allow the user to specify invalid destination
function customcontexts_get_config($engine) {
  global $ext;
  switch($engine) {
    case 'asterisk':
	global $db;
	$sql = "select context, dialrules, faildestination, featurefaildestination, failpin, featurefailpin from customcontexts_contexts";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) 
		$results = null;
	foreach ($results as $val) {
		$context = $val[0];
//		$ext->_exts[$context][] = null;
//partially stolen from outbound routing
		$dialpattern = explode("\n",$val[1]);
		if (!$dialpattern) {
			$dialpattern = array();
		}
		foreach (array_keys($dialpattern) as $key) {
			//trim it
			$dialpattern[$key] = trim($dialpattern[$key]);
			
			// remove blanks
			if ($dialpattern[$key] == "") unset($dialpattern[$key]);
			
			// remove leading underscores (we do that on backend)
			if ($dialpattern[$key][0] == "_") $dialpattern[$key] = substr($dialpattern[$key],1);
		}
		// check for duplicates, and re-sequence
		$dialpattern = array_values(array_unique($dialpattern));
		if (is_array($dialpattern)) {
			foreach ($dialpattern as $pattern) {
				if (false !== ($pos = strpos($pattern,"|"))) {
					// we have a | meaning to not pass the digits on
					// (ie, 9|NXXXXXX should use the pattern _9NXXXXXX but only pass NXXXXXX, not the leading 9)
					
					$pattern = str_replace("|","",$pattern); // remove all |'s
					$exten = "EXTEN:".$pos; // chop off leading digit
				} else {
					// we pass the full dialed number as-is
					$exten = "EXTEN"; 
				}
				
				if (!preg_match("/^[0-9*]+$/",$pattern)) { 
					// note # is not here, as asterisk doesn't recoginize it as a normal digit, thus it requires _ pattern matching
					
					// it's not strictly digits, so it must have patterns, so prepend a _
					$pattern = "_".$pattern;
				}
				$ext->add($context,$pattern, '', new ext_goto('1','${'.$exten.'}',$context.'_rulematch')); 
			}
		}
//switch to first line to deny all access when time group deleted
//		$sql = "select include, time from customcontexts_includes left outer join customcontexts_timegroups_detail on  customcontexts_includes.timegroupid = customcontexts_timegroups_detail.timegroupid where context = '".$context."' and (customcontexts_includes.timegroupid is null or customcontexts_timegroups_detail.timegroupid is not null) order by sort";
		$sql = "select include, time, userules from customcontexts_includes left outer join customcontexts_timegroups_detail on  customcontexts_includes.timegroupid = customcontexts_timegroups_detail.timegroupid where context = '".$context."' order by sort";
		$results2 = $db->getAll($sql);
		if(DB::IsError($results2)) 
			$results2 = null;
		foreach ($results2 as $inc) {
			$time = isset($inc[1])?'|'.$inc[1]:'';
			switch ($inc[2]) {
				case 'allowmatch':
					if (is_array($dialpattern)) {
						$ext->addInclude($context.'_rulematch',$inc[0].$time);
					}
				break;
				case 'denymatch':
					$ext->addInclude($context,$inc[0].$time);
				break;
				default:
					$ext->addInclude($context,$inc[0].$time);
					if (is_array($dialpattern)) {
						$ext->addInclude($context.'_rulematch',$inc[0].$time);
					}
				break;
			}
		}
//these go in funny "exten => s,1,Macro(hangupcall,)"
//i'd rather use the base extension class to type it normally, but there is a bug in the class see ticket http://www.freepbx.org/trac/ticket/1453
		$ext->add($context,'s', '', new ext_macro('hangupcall')); 
		$ext->add($context,'h', '', new ext_macro('hangupcall'));
		$ext->addInclude($context,$context.'_bad-number');
		$ext->addInclude($context,'bad-number');
		if (is_array($dialpattern)) {
			$ext->add($context.'_rulematch','s', '', new ext_macro('hangupcall')); 
			$ext->add($context.'_rulematch','h', '', new ext_macro('hangupcall'));
			$ext->addInclude($context.'_rulematch',$context.'_bad-number');
			$ext->addInclude($context.'_rulematch','bad-number');
		}
		$ext->_exts[$context.'_bad-number'][] = null;
		if (isset($val[2]) && (!$val[2] == '')) {
			$goto = explode(',',$val[2]);
			if (isset($val[4]) && ($val[4] <> '')) {
				$ext->add($context.'_bad-number', '_X.', '', new ext_authenticate($val[4]));
			}
			$ext->add($context.'_bad-number', '_X.', '', new ext_goto($goto[2],$goto[1],$goto[0]));
		}
		if (isset($val[3]) && (!$val[3] == '')) {
			$goto = explode(',',$val[3]);
			if (isset($val[5]) && ($val[5] <> '')) {
				$ext->add($context.'_bad-number', '_*.', '', new ext_authenticate($val[5]));
			}
			$ext->add($context.'_bad-number', '_*.', '', new ext_goto($goto[2],$goto[1],$goto[0]));
		}
	}
  break;
  }
}

// returns a associative arrays with keys 'destination' and 'description'
// it allows custom contexts to be chosen as destinations
//this may seem a bit strange, but it works simply it sends the user to the EXTEN he dialed (or IVR option) within the selected context
function customcontexts_destinations() {
	$contexts =  customcontexts_getcontexts();
	$extens[] = array('destination' => 'from-internal,${EXTEN},1', 'description' => 'Full Internal Access');
	if (is_array($contexts)) {
		foreach ($contexts as $r) {
			$extens[] = array('destination' => $r[0].',${EXTEN},1', 'description' => $r[1]);
		}
	}

	return $extens;
}

//brute force hoook to devices and extensions pages to inform the user that they can place these devices in their custom contexts
function customcontexts_configpageinit($dispnum) {
global $currentcomponent;
	switch ($dispnum) {
		case 'devices':
			$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
			if ($extdisplay != '') {
			$contextssel  = customcontexts_getcontexts();
			$currentcomponent->addoptlistitem('contextssel', 'from-internal', 'ALLOW ALL (Default)');
			foreach ($contextssel as $val) {
				$currentcomponent->addoptlistitem('contextssel', $val[0], $val[1]);
			}
			$currentcomponent->setoptlistopts('contextssel', 'sort', false);
			$currentcomponent->addguifunc('customcontexts_devices_configpageload');
			}
		break;
		case 'extensions':
			$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
			if ($extdisplay != '') {
			$contextssel  = customcontexts_getcontexts();
			$currentcomponent->addoptlistitem('contextssel', 'from-internal', 'ALLOW ALL (Default)');
			foreach ($contextssel as $val) {
				$currentcomponent->addoptlistitem('contextssel', $val[0], $val[1]);
			}
			$currentcomponent->setoptlistopts('contextssel', 'sort', false);
			$currentcomponent->addguifunc('customcontexts_extensions_configpageload');
			}
		break;
	}
}

//hook gui function
function customcontexts_devices_configpageload() {
global $currentcomponent;
//should get current context if possible
	$curcontext = '';
	$currentcomponent->addguielem('Device Options', new gui_selectbox('customcontext', $currentcomponent->getoptlist('contextssel'), $curcontext, 'Custom Context', 'You have the '.customcontexts_getmodulevalue('moduledisplayname').' Module installed! You can select a custom context from this list to limit this user to portions of the dialplan you defined in the '.customcontexts_getmodulevalue('moduledisplayname').' module.',true, "javascript:if (document.frm_devices.customcontext.value) {document.frm_devices.devinfo_context.value = document.frm_devices.customcontext.value} else {document.frm_devices.devinfo_context.value = 'from-internal'}"));
}

//hook gui function
function customcontexts_extensions_configpageload() {
global $currentcomponent;
//should get current context if possible
	$curcontext = '';
	$currentcomponent->addguielem('Device Options', new gui_selectbox('customcontext', $currentcomponent->getoptlist('contextssel'), $curcontext, 'Custom Context', 'You have the '.customcontexts_getmodulevalue('moduledisplayname').' Module installed! You can select a custom context from this list to limit this user to portions of the dialplan you defined in the '.customcontexts_getmodulevalue('moduledisplayname').' module.',true, "javascript:if (document.frm_extensions.customcontext.value) {document.frm_extensions.devinfo_context.value = document.frm_extensions.customcontext.value} else {document.frm_extensions.devinfo_context.value = 'from-internal'}"));
}

//admin page helper
//we are using gui styles so there is very little on the page
//the admin page is used to list _existing_ contexts for us to parse for includes
//these contexts/includes can be tagged with a description for the user to select on the custom contexts page
function customcontexts_customcontextsadmin_configpageinit($dispnum) {
global $currentcomponent;
	switch ($dispnum) {
		case 'customcontextsadmin':
			$currentcomponent->addguifunc('customcontexts_customcontextsadmin_configpageload');
			$currentcomponent->addprocessfunc('customcontexts_customcontextsadmin_configprocess', 5);  
		break;
	}
}

//this is the dirty work displaying the admin page
function customcontexts_customcontextsadmin_configpageload() {
global $currentcomponent;
	$contexterr = 'Context may not be left blank and must contain only letters, numbers and a few select characters!';
	$descerr = 'Description must be alpha-numeric, and may not be left blank';
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$action= isset($_REQUEST['action'])?$_REQUEST['action']:null;
	if ($action == 'del') {
		$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Context").": $extdisplay"." deleted!", false), 0);
	}
	else
	{
//need to get module name/type dynamically
		$query = ($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'type=tool&display=customcontextsadmin&extdisplay='.$extdisplay;
		$delURL = $_SERVER['PHP_SELF'].'?'.$query.'&action=del';
		$info = 'The context which contains includes which you would like to make available to '.customcontexts_getmodulevalue('moduledisplayname').'. Any context you enter here will be parsed for includes and you can then include them in your own '.customcontexts_getmodulevalue('moduledisplayname').'. Removing them here does NOT delete the context, rather makes them unavailable to your '.customcontexts_getmodulevalue('moduledisplayname').'.';
	       $currentcomponent->addguielem('_top', new gui_hidden('action', ($extdisplay ? 'edit' : 'add')));
		$currentcomponent->addguielem('_bottom', new gui_link('del', _(customcontexts_getmodulevalue('moduledisplayname')." v".customcontexts_getmodulevalue('moduleversion')), 'http://aussievoip.com.au/wiki/freePBX-CustomContexts', true, false), 0);
		if (!$extdisplay) {
			$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Add Context"), false), 0);
			$currentcomponent->addguielem('Context', new gui_textbox('extdisplay', '', 'Context', $info, 'isWhitespace() || !isFilename()', $contexterr, false), 3);
			$currentcomponent->addguielem('Context', new gui_textbox('description', '', 'Description', 'This will display as a heading for the available includes on the '.customcontexts_getmodulevalue('moduledisplayname').' page.', '!isAlphanumeric() || isWhitespace()', $descerr, false), 3);
		}
		else
		{
			$savedcontext = customcontexts_customcontextsadmin_get($extdisplay);
			$context = $savedcontext[0];
			$description = $savedcontext[1];
			$locked = $savedcontext[2];
			$currentcomponent->addguielem('_top', new gui_hidden('extdisplay', $extdisplay));
			$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Edit Context").": $description", false), 0);
			if ($locked == false) {			
				$currentcomponent->addguielem('_top', new gui_link('del', _("Remove Context").": $context", $delURL, true, false), 0);
			}
			else
			{
				$currentcomponent->addguielem('_top', new gui_label('del', _("Context").": $context can not be removed!", $delURL, true, false), 0);
			}
			$currentcomponent->addguielem('Context', new gui_textbox('description', $description, 'Description', 'This will display as a heading for the available includes on the '.customcontexts_getmodulevalue('moduledisplayname').' page.', '!isAlphanumeric() || isWhitespace()', $descerr, false), 3);
			$inclist = customcontexts_getincludeslist($extdisplay);
			foreach ($inclist as $val) {
				if ($val[2] > 0) {
//$gui1 = new gui_textbox('includes['.$val[0].']', $val[1], '<font color="red"><strong>'.$val[0].'</strong></font>', 'This will display as the name of the include on the '.customcontexts_getmodulevalue('moduledisplayname').' page.<BR><font color="red"><strong>NOTE: This include should have a description denoting the fact that allowing it may allow another ENTIRE context!</strong></font>', '!isAlphanumeric() || isWhitespace()', $descerr, false);
//$inchtml = $gui1->generatehtml();
//$inchtml = '<tr><td colspan="2"><table><tr><td colspan="2">'.$inchtml.'</td></tr></table></td></tr>'.$inchtml;
//$currentcomponent->addguielem('Includes Descriptions', new guielement('$val[0]',$inchtml,''),3);
					$currentcomponent->addguielem('Includes Descriptions', new gui_textbox('includes['.$val[0].']', $val[1], '<font color="red"><strong>'.$val[0].'</strong></font>', 'This will display as the name of the include on the '.customcontexts_getmodulevalue('moduledisplayname').' page.<BR><font color="red"><strong>NOTE: This include should have a description denoting the fact that allowing it may allow another ENTIRE context!</strong></font>', '!isAlphanumeric() || isWhitespace()', $descerr, false), 3);
				} else {
					$currentcomponent->addguielem('Includes Descriptions', new gui_textbox('includes['.$val[0].']', $val[1], $val[0], 'This will display as the name of the include on the '.customcontexts_getmodulevalue('moduledisplayname').' page.', '!isAlphanumeric() || isWhitespace()', $descerr, false), 3);
				}

			}
		}
	}
}


//handle the admin submit button
function customcontexts_customcontextsadmin_configprocess() {
	$action= isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$context= isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$description= isset($_REQUEST['description'])?$_REQUEST['description']:null;
//addslashes	
	switch ($action) {
	case 'add':
		customcontexts_customcontextsadmin_add($context,$description);
	break;
	case 'edit':
		customcontexts_customcontextsadmin_edit($context,$description);
		$includes = isset($_REQUEST['includes'])?$_REQUEST['includes']:null;
		customcontexts_customcontextsadmin_editincludes($context,$includes);
	break;
	case 'del':
		customcontexts_customcontextsadmin_del($context);
	break;
	}
}


//retrieve a single context for the admin page
function customcontexts_customcontextsadmin_get($context) {
	global $db;
	$sql = "select context, description, locked from customcontexts_contexts_list where context = '$context'";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
 		$results = null;
	}
	$tmparray = array($results[0][0], $results[0][1], $results[0][2]);
	return $tmparray;
}

//add a single context for admin
function customcontexts_customcontextsadmin_add($context,$description) {
	global $db;
	$sql = "insert customcontexts_contexts_list (context, description) VALUES ('$context','$description')";
	$db->query($sql);
	needreload();
}

//del a single context from admin
function customcontexts_customcontextsadmin_del($context) {
	global $db;
	$sql = "delete from customcontexts_includes_list where context = '$context'";
	$db->query($sql);
	$sql = "delete from customcontexts_contexts_list where context = '$context'";
	$db->query($sql);
	needreload();
}

//update a single context for admin
function customcontexts_customcontextsadmin_edit($context,$description) {
	global $db;
	$sql = "update customcontexts_contexts_list set description = '$description' where context = '$context'";
	$db->query($sql);
	needreload();
}

//edit the includes under a single admin context
function customcontexts_customcontextsadmin_editincludes($context,$includes) {
	global $db;
	$sql = "delete from customcontexts_includes_list  where context = '$context'";
	$db->query($sql);
	foreach ($includes as $key=>$val) {
		$sql = "insert customcontexts_includes_list (context, include, description) values ('$context','$key','$val')";
		$db->query($sql);
	}
	needreload();
}

//---------------------------------------------

//custom contexts page helper
//we are using gui styles so there is very little on the page
//the custom contexts page is used to create _new_ contexts for use in the dialplan
//these contexts can include any includes which were made available from admin
function customcontexts_customcontexts_configpageinit($dispnum) {
global $currentcomponent;
	switch ($dispnum) {
		case 'customcontexts':
			$currentcomponent->addoptlistitem('includeyn', 'yes', 'Allow');
			$currentcomponent->addoptlistitem('includeyn', 'no', 'Deny');
			$currentcomponent->addoptlistitem('includeyn', 'allowmatch', 'Allow Rules');
			$currentcomponent->addoptlistitem('includeyn', 'denymatch', 'Deny Rules');
			$timegroups = customcontexts_gettimegroups();
			foreach ($timegroups as $val) {
				$currentcomponent->addoptlistitem('includeyn', $val[0], $val[1]);
			}
			$currentcomponent->setoptlistopts('includeyn', 'sort', false);
			for($i = 0; $i <= 100; $i++) { 
				$currentcomponent->addoptlistitem('includesort', $i - 50, $i);
			}
			$currentcomponent->addguifunc('customcontexts_customcontexts_configpageload');
			$currentcomponent->addprocessfunc('customcontexts_customcontexts_configprocess', 5);  
		break;
	}
}

//actually render the custom contexts page
function customcontexts_customcontexts_configpageload() {
global $currentcomponent;
	$contexterr = 'Context may not be left blank and must contain only letters, numbers and a few select characters!';
	$descerr = 'Description must be alpha-numeric, and may not be left blank';
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$action= isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$showsort = isset($_REQUEST['showsort'])?$_REQUEST['showsort']:null;
	if (isset($showsort) && $showsort <> customcontexts_getmodulevalue('displaysortforincludes')) {
		customcontexts_setmodulevalue('displaysortforincludes', $showsort);
	}
	if ($action == 'del') {
		$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Context").": $extdisplay"." deleted!", false), 0);
	}
	else
	{
//need to get page name/type dynamically
//caused trouble on dup or del after dup or rename
//		$query = ($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'type=setup&display=customcontexts&extdisplay='.$extdisplay;
		$query = 'type=setup&display=customcontexts&extdisplay='.$extdisplay;
		$delURL = $_SERVER['PHP_SELF'].'?'.$query.'&action=del';
		$dupURL = $_SERVER['PHP_SELF'].'?'.$query.'&action=dup';
		$info = 'The custom context to make will be available in your dialplan. These contexts can be used as a context for a device/extension to allow them limited access to your dialplan.';
		$currentcomponent->addguielem('_bottom', new gui_link('ver', _(customcontexts_getmodulevalue('moduledisplayname')." v".customcontexts_getmodulevalue('moduleversion')), 'http://aussievoip.com.au/wiki/freePBX-CustomContexts', true, false), 0);
		if (!$extdisplay) {
			$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Add Context"), false), 0);
			$currentcomponent->addguielem('Context', new gui_textbox('extdisplay', '', 'Context', $info, 'isWhitespace() || !isFilename()', $contexterr, false), 3);
			$currentcomponent->addguielem('Context', new gui_textbox('description', '', 'Description', 'This will display as the name of this custom context.', '!isAlphanumeric() || isWhitespace()', $descerr, false), 3);
		}
		else
		{
			$savedcontext = customcontexts_customcontexts_get($extdisplay);
			$context = $savedcontext[0];
			$description = $savedcontext[1];
			$rulestext = $savedcontext[2];
			$faildest  = $savedcontext[3];
			$featurefaildest  = $savedcontext[4];
			$failpin  = $savedcontext[5];
			$featurefailpin  = $savedcontext[6];
			$currentcomponent->addguielem('_top', new gui_hidden('extdisplay', $extdisplay));
			$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Edit Context").": $description", false), 0);
//			$currentcomponent->addguielem('_top', new gui_link('del', _("Delete Context")." $context", $delURL, true, false), 0);
			$currentcomponent->addguielem('_top', new guielement('del', '<tr><td colspan ="2"><a href="'.$delURL.'" onclick="return confirm(\'Are you sure you want to delete '.$context.'?\')">Delete Context '.$context.'</a></td></tr>', ''),3);
			$currentcomponent->addguielem('_top', new gui_link('dup', _("Duplicate Context")." $context", $dupURL, true, false), 3);
			$showsort = customcontexts_getmodulevalue('displaysortforincludes');
			if ($showsort == 1) {
//				$sortURL = $_SERVER['PHP_SELF'].'?'.$query.'&showsort=0';
//				$currentcomponent->addguielem('_top', new gui_link('showsort', "Hide Sort Option", $sortURL, true, false), 0);
			} else {
				$sortURL = $_SERVER['PHP_SELF'].'?'.$query.'&showsort=1';
				$currentcomponent->addguielem('_top', new gui_link('showsort', "Show Sort Option", $sortURL, true, false), 0);
			}
			$currentcomponent->addguielem('Context', new gui_textbox('newcontext', $extdisplay, 'Context', $info, 'isWhitespace() || !isFilename()', $contexterr, false), 2);
			$currentcomponent->addguielem('Context', new gui_textbox('description', $description, 'Description', 'This will display as the name of this custom context.', '', '', false), 2);
			$ruledesc = 'If defined, you will have the option for each portion of the dialplan to Allow Rule, and that inclued will only be available if the number dialed matches these rules, or Deny Rule, and that include will only be available if the dialed number does NOT match these rules. You may use a pipe | to strip the preceeding digits.';
			$ruleshtml = '<tr><td valign="top"><a href="#" class="info">Dial Rules<span>'.$ruledesc.'</span></a></td><td><textarea cols="20" rows="5" id="dialpattern" name="dialpattern">'.$rulestext.'</textarea></td></tr>';
			$currentcomponent->addguielem('Context', new guielement('rulesbox',$ruleshtml,''), 3);
//			!isDialpattern
			$currentcomponent->addguielem('Failover Destination', new gui_textbox('failpin', $failpin, 'PIN', 'Enter a numeric PIN to require authentication before continuing to destination.', '!isPINList()', 'PIN must be numeric!', true), 4);
			$selhtml = drawselects($faildest ,0);
			$currentcomponent->addguielem('Failover Destination', new guielement('dest0', $selhtml, ''),4);
			$currentcomponent->addguielem('Feature Code Failover Destination', new gui_textbox('featurefailpin', $featurefailpin, 'PIN', 'Enter a numeric PIN to require authentication before continuing to destination.', '!isPINList()', 'PIN must be numeric!', true), 4);
			$selhtml = drawselects($featurefaildest ,1);
			$currentcomponent->addguielem('Feature Code Failover Destination', new guielement('dest1', $selhtml, ''),4);
			$currentcomponent->addguielem('Set All', new gui_selectbox('setall', $currentcomponent->getoptlist('includeyn'), '', 'Set All To:', 'Choose allow to allow access to all includes, choose deny to deny access.',true,'javascript:for (i=0;i<document.forms[\'frm_customcontexts\'].length;i++) {if(document.forms[\'frm_customcontexts\'][i].type==\'select-one\' && document.forms[\'frm_customcontexts\'][i].name.indexOf(\'[allow]\') >= 0 ) {document.forms[\'frm_customcontexts\'][i].selectedIndex = document.forms[\'frm_customcontexts\'][\'setall\'].selectedIndex-1;}}'),2);
			$inclist = customcontexts_getincludes($extdisplay);
			foreach ($inclist as $val) {
				if ($showsort == 1) {
//$gui1 = new gui_textbox('includes['.$val[0].']', $val[1], '<font color="red"><strong>'.$val[0].'</strong></font>', 'This will display as the name of the include on the '.customcontexts_getmodulevalue('moduledisplayname').' page.<BR><font color="red"><strong>NOTE: This include should have a description denoting the fact that allowing it may allow another ENTIRE context!</strong></font>', '!isAlphanumeric() || isWhitespace()', $descerr, false);
//$inchtml = $gui1->generatehtml();
//$inchtml = '<tr><td colspan="2"><table><tr><td colspan="2">'.$inchtml.'</td></tr></table></td></tr>'.$inchtml;
//$currentcomponent->addguielem('Includes Descriptions', new guielement('$val[0]',$inchtml,''),3);
					if ($val[6] > 0) {
//						$currentcomponent->addguielem($val[1], new gui_selectbox('includes['.$val[2].'][allow]', $currentcomponent->getoptlist('includeyn'), $val[4], '<font color="red"><strong>'.$val[3].'</strong></font>', $val[2].': Choose allow to allow access to this include, choose deny to deny access.<BR><font color="red"><strong>NOTE: Allowing this include may automatically allow another ENTIRE context!</strong></font>',false));
						$gui1 = new gui_selectbox('includes['.$val[2].'][allow]', $currentcomponent->getoptlist('includeyn'), $val[4], '<font color="red"><strong>'.$val[3].'</strong></font>', $val[2].': Choose allow to allow access to this include, choose deny to deny access.<BR><font color="red"><strong>NOTE: Allowing this include may automatically allow another ENTIRE context!</strong></font>',false);
					} else {
//						$currentcomponent->addguielem($val[1], new gui_selectbox('includes['.$val[2].'][allow]', $currentcomponent->getoptlist('includeyn'), $val[4], $val[3], $val[2].': Choose allow to allow access to this include, choose deny to deny access.',false));
						$gui1 = new gui_selectbox('includes['.$val[2].'][allow]', $currentcomponent->getoptlist('includeyn'), $val[4], $val[3], $val[2].': Choose allow to allow access to this include, choose deny to deny access.',false);
					}
//					$currentcomponent->addguielem($val[1], new gui_selectbox('includes['.$val[2].'][sort]', $currentcomponent->getoptlist('includesort'), $val[5], '<div align="right">Priority</div>', 'Choose a priority with which to sort this option. Lower numbers have a higher priority.',false));
					$guisort = new gui_selectbox('includes['.$val[2].'][sort]', $currentcomponent->getoptlist('includesort'), $val[5], '<div align="right">Priority</div>', 'Choose a priority with which to sort this option. Lower numbers have a higher priority.',false);
					$inchtml = '<tr><td colspan="2"><table width="100%"><tr><td></td><td width="50"></td></tr>'.$gui1->generatehtml().'</table></td><td><table>'.$guisort->generatehtml().'</table></td></tr>';
					$currentcomponent->addguielem($val[1], new guielement('$val[0]',$inchtml,''),3);
				} else {
					if ($val[6] > 0) {
						$currentcomponent->addguielem($val[1], new gui_selectbox('includes['.$val[2].'][allow]', $currentcomponent->getoptlist('includeyn'), $val[4], '<font color="red"><strong>'.$val[3].'</strong></font>', $val[2].': Choose allow to allow access to this include, choose deny to deny access.<BR><font color="red"><strong>NOTE: Allowing this include may automatically allow another ENTIRE context!</strong></font>',false));
					} else {
						$currentcomponent->addguielem($val[1], new gui_selectbox('includes['.$val[2].'][allow]', $currentcomponent->getoptlist('includeyn'), $val[4], $val[3], $val[2].': Choose allow to allow access to this include, choose deny to deny access.',false));
					}
				}
			}
		}
	}
       $currentcomponent->addguielem('_top', new gui_hidden('action', ($extdisplay ? 'edit' : 'add')));
}

//handle custom contexts page submit button
function customcontexts_customcontexts_configprocess() {
	$action= isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$context= isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$newcontext= isset($_REQUEST['newcontext'])?$_REQUEST['newcontext']:null;
	$description= isset($_REQUEST['description'])?$_REQUEST['description']:null;
	$dialrules= isset($_REQUEST['dialpattern'])?$_REQUEST['dialpattern']:null;
	$faildest= isset($_REQUEST["goto0"])?$_REQUEST[$_REQUEST['goto0'].'0']:null;
	$featurefaildest= isset($_REQUEST["goto1"])?$_REQUEST[$_REQUEST['goto1'].'1']:null;
	$failpin= isset($_REQUEST['failpin'])?$_REQUEST['failpin']:null;
	$featurefailpin= isset($_REQUEST['featurefailpin'])?$_REQUEST['featurefailpin']:null;

//addslashes	
	switch ($action) {
	case 'add':
		customcontexts_customcontexts_add($context,$description,$dialrules,$faildest,$featurefaildest,$failpin,$featurefailpin);
	break;
	case 'edit':
		if ($context <> $newcontext) {
			$_REQUEST['extdisplay'] = isset($_REQUEST['extdisplay'])?$newcontext:null;
		}
		customcontexts_customcontexts_edit($context,$newcontext,$description,$dialrules,$faildest,$featurefaildest,$failpin,$featurefailpin);
		$includes = isset($_REQUEST['includes'])?$_REQUEST['includes']:null;
		customcontexts_customcontexts_editincludes($context,$includes,$newcontext);
	break;
	case 'del':
		customcontexts_customcontexts_del($context);
		$_REQUEST['extdisplay'] = null;
	break;
	case 'dup':
		$newcontext = customcontexts_customcontexts_duplicatecontext($context);
		if ($context <> $newcontext) {
			$_REQUEST['extdisplay'] = isset($_REQUEST['extdisplay'])?$newcontext:null;
		}
	break;
	}
}

//retrieve a single custom context for the custom contexts page
function customcontexts_customcontexts_get($context) {
	global $db;
	$sql = "select context, description, dialrules, faildestination, featurefaildestination, failpin, featurefailpin from customcontexts_contexts where context = '$context'";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
 		$results = null;
	}
	$tmparray = array($results[0][0], $results[0][1], $results[0][2], $results[0][3], $results[0][4], $results[0][5], $results[0][6]);
	return $tmparray;
}

//add a new custom context for custom contexts page
function customcontexts_customcontexts_add($context,$description,$dialrules,$faildest,$featurefaildest,$failpin,$featurefailpin) {
	global $db;
	$sql = "insert customcontexts_contexts (context, description, dialrules, faildestination, featurefaildestination, failpin, featurefailpin) VALUES ('$context','$description','$dialrules','$faildest','$featurefaildest','$failpin','$featurefailpin')";
	$db->query($sql);
	needreload();
}

//delete a single custom context from the custom contexts page
function customcontexts_customcontexts_del($context) {
	global $db;
	$sql = "delete from customcontexts_includes where context = '$context'";
	$db->query($sql);
	$sql = "delete from customcontexts_contexts where context = '$context'";
	$db->query($sql);
	needreload();
}

//update a single custom context from the custom contexts page
function customcontexts_customcontexts_edit($context,$newcontext,$description,$dialrules,$faildest,$featurefaildest,$failpin,$featurefailpin) {
	global $db;
	if (!isset($newcontext) || ($newcontext == '')) {
		$newcontext = $context;
	}
	$sql = "update customcontexts_contexts set context = '$newcontext', description = '$description', dialrules = '$dialrules', faildestination = '$faildest', featurefaildestination = '$featurefaildest', failpin = '$failpin', featurefailpin = '$featurefailpin' where context = '$context'";
	$db->query($sql);
	needreload();
}

//update the includes under a single custom context from the custom contexts page
function customcontexts_customcontexts_editincludes($context,$includes,$newcontext) {
	global $db;
	$sql = "delete from customcontexts_includes  where context = '$context'";
	$db->query($sql);
	if (!isset($newcontext) || ($newcontext == '')) {
		$newcontext = $context;
	}
	foreach ($includes as $key=>$val) {
		if ($val[allow] <> 'no') {
			$timegroup = 'null';
			$sort = 0;
			$userules = null;
			if (is_numeric($val[allow])) {
				$timegroup = $val[allow];
			} else {
				if ($val[allow] <> 'yes') {
					$userules = $val[allow];
				}
			}
			if (is_numeric($val[sort])) {
				$sort = $val[sort];
			}
			$sql = "insert customcontexts_includes (context, include, timegroupid, sort, userules) values ('$newcontext','$key', $timegroup, $sort, '$userules')";
			$db->query($sql);
		}
	}
	needreload();
}

function customcontexts_customcontexts_duplicatecontext($context) {
	global $db;
	$suffix = '_2';
	$counter = 2;
	$sql = "select description, dialrules, faildestination, featurefaildestination, failpin, featurefailpin from customcontexts_contexts  where context = '$context'";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
 		$results = null;
		return;
	}
	$description = $results[0][0];
	$dialrules = $results[0][1];
	$faildest = $results[0][2];
	$featurefaildest = $results[0][3];
	$failpin = $results[0][4];
	$featurefailpin = $results[0][5];
	$sql = "select count(*) from customcontexts_contexts  where context = '".$context.$suffix."' or description = '".$description.$suffix."'";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
 		$results = null;
	}
	while ($results[0][0] > 0) {
		$counter = $counter + 1;
		$suffix = '_'.$counter;
		$sql = "select count(*) from customcontexts_contexts  where context = '".$context.$suffix."' or description = '".$description.$suffix."'";
		$results = $db->getAll($sql);
		if(DB::IsError($results)) {
	 		$results = null;
		}
	}
	customcontexts_customcontexts_add($context.$suffix,$description.$suffix,$dialrules,$faildest,$featurefaildest,$failpin,$featurefailpin);
	$includes = customcontexts_getincludes($context);
	foreach ($includes as $val) {
		$newincludes[$val[2]] = array("allow"=>"$val[4]", "sort"=>"$val[5]");
	}
	customcontexts_customcontexts_editincludes($context.$suffix,$newincludes,$context.$suffix);
	needreload();
	return $context.$suffix;
}

//---------------------------------------------

//custom contexts timegroups page helper
//we are using gui styles so there is very little on the page
//the custom contexts timegroups page is used to create time conditions 
//to allow the user to limit when to include each context on the custom contexts page
function customcontexts_customcontextstimes_configpageinit($dispnum) {
global $currentcomponent;
	switch ($dispnum) {
		case 'customcontextstimes':
			$currentcomponent->addguifunc('customcontexts_customcontextstimes_configpageload');
			$currentcomponent->addprocessfunc('customcontexts_customcontextstimes_configprocess', 5);  
		break;
	}
}

//actually render the custom contexts times page
function customcontexts_customcontextstimes_configpageload() {
global $currentcomponent;
	$descerr = 'Description must be alpha-numeric, and may not be left blank';
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$action= isset($_REQUEST['action'])?$_REQUEST['action']:null;
	if ($action == 'del') {
		$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Time Group").": $extdisplay"." deleted!", false), 0);
	}
	else
	{
//need to get page name/type dynamically
		$query = ($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'type=setup&display=customcontextstimes&extdisplay='.$extdisplay;
		$delURL = $_SERVER['PHP_SELF'].'?'.$query.'&action=del';
		$info = '';
		$currentcomponent->addguielem('_bottom', new gui_link('del', _(customcontexts_getmodulevalue('moduledisplayname')." v".customcontexts_getmodulevalue('moduleversion')), 'http://aussievoip.com.au/wiki/freePBX-CustomContexts', true, false), 0);
		if (!$extdisplay) {
			$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Add Time Group"), false), 0);
			$currentcomponent->addguielem('Time Group', new gui_textbox('description', '', 'Description', 'This will display as the name of this Time Group.', '!isAlphanumeric() || isWhitespace()', $descerr, false), 3);
		}
		else
		{
			$savedtimegroup= customcontexts_customcontextstimes_get($extdisplay);
			$timegroup = $savedtimegroup[0];
			$description = $savedtimegroup[1];
			$currentcomponent->addguielem('_top', new gui_hidden('extdisplay', $extdisplay));
			$currentcomponent->addguielem('_top', new gui_pageheading('title', _("Edit Time Group").": $description", false), 0);
			$currentcomponent->addguielem('_top', new gui_link('del', _("Delete Time Group")." $timegroup", $delURL, true, false), 0);
//			$currentcomponent->addguielem('_top', new gui_subheading('subtitle', "Time Group", false), 0);
			$currentcomponent->addguielem('Time Group', new gui_textbox('description', $description, 'Description', 'This will display as the name of this Time Group.', '', '', false), 3);
			$timelist = customcontexts_gettimes($extdisplay);
			foreach ($timelist as $val) {
//add gui here
			$timehtml = drawtimeselects('times['.$val[0].']',$val[1]);
			$timehtml = '<tr><td colspan="2"><table>'.$timehtml.'</table></td></tr>';
			$currentcomponent->addguielem($val[1], new guielement('dest0', $timehtml, ''),5);
			}
		$timehtml = drawtimeselects('times[new]',null);
		$timehtml = '<tr><td colspan="2"><table>'.$timehtml.'</table></td></tr>';
		$currentcomponent->addguielem('New Time', new guielement('dest0', $timehtml, ''),6);
		}
	}
       $currentcomponent->addguielem('_top', new gui_hidden('action', ($extdisplay ? 'edit' : 'add')));
}

//handle custom contexts times page submit button
function customcontexts_customcontextstimes_configprocess() {
	$action= isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$timegroup= isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$description= isset($_REQUEST['description'])?$_REQUEST['description']:null;
//addslashes	
	switch ($action) {
	case 'add':
		customcontexts_customcontextstimes_add($description);
	break;
	case 'edit':
		customcontexts_customcontextstimes_edit($timegroup,$description);
		$times = isset($_REQUEST['times'])?$_REQUEST['times']:null;
		customcontexts_customcontextstimes_edittimes($timegroup,$times);
	break;
	case 'del':
		customcontexts_customcontextstimes_del($timegroup);
	break;
	}
}

//retrieve a single timegroup for the custom contexts times page
function customcontexts_customcontextstimes_get($timegroup) {
	global $db;
	$sql = "select id, description from customcontexts_timegroups where id = $timegroup";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
 		$results = null;
	}
	$tmparray = array($results[0][0], $results[0][1]);
	return $tmparray;
}

//add a new timegroup for custom contexts times page
function customcontexts_customcontextstimes_add($description) {
	global $db;
	$sql = "insert customcontexts_timegroups(description) VALUES ('$description')";
	$db->query($sql);
	needreload();
}

//delete a single timegroup from the custom contexts times page
function customcontexts_customcontextstimes_del($timegroup) {
	global $db;
	$sql = "delete from customcontexts_timegroups_detail where timegroupid = $timegroup";
	$db->query($sql);
	$sql = "delete from customcontexts_timegroups where id = $timegroup";
	$db->query($sql);
	needreload();
}

//update a single timegroup from the custom contexts times page
function customcontexts_customcontextstimes_edit($timegroup,$description) {
	global $db;
	$sql = "update customcontexts_timegroups set description = '$description' where id = $timegroup";
	$db->query($sql);
	needreload();
}

//update the timegroup_detail under a single timegroup from the custom contexts times page
function customcontexts_customcontextstimes_edittimes($timegroup,$times) {
	global $db;
	$sql = "delete from customcontexts_timegroups_detail where timegroupid = $timegroup";
	$db->query($sql);
	foreach ($times as $key=>$val) {
		extract($val);
		$time = customcontexts_customcontextstimes_buildtime( $hour_start, $minute_start, $hour_finish, $minute_finish, $wday_start, $wday_finish, $mday_start, $mday_finish, $month_start, $month_finish);
		if (isset($time) && $time <> '*|*|*|*') {
			$sql = "insert customcontexts_timegroups_detail (timegroupid, time) values ($timegroup, '$time')";
			$db->query($sql);
		}
	}
	needreload();
}

//---------------------------------stolen from time conditions------------------------------------------

function drawtimeselects($name, $time){
	$html = '';
	// ----- Load Time Pattern Variables -----
	if (isset($time)) {
		list($time_hour, $time_wday, $time_mday, $time_month) = explode('|', $time);
	} else {
		list($time_hour, $time_wday, $time_mday, $time_month) = Array('*','-','-','-');
	}
	$html = $html.'<tr>';
	$html = $html.'<td>'._("Time to start:").'</td>';
	$html = $html.'<td>';
	// Hour could be *, hh:mm, hh:mm-hhmm
	if ( $time_hour === '*' ) {
		$hour_start = $hour_finish = '-';
		$minute_start = $minute_finish = '-';
	} else {
		list($hour_start_string, $hour_finish_string) = explode('-', $time_hour);
		if ($hour_start_string === '*') {$hour_start_string = $hour_finish_string;}
		if ($hour_finish_string === '*') {$hour_finish_string = $hour_start_string;}
		list($hour_start, $minute_start) = explode( ':', $hour_start_string);
		list($hour_finish, $minute_finish) = explode( ':', $hour_finish_string);
		if ( !$hour_finish) $hour_finish = $hour_start;
		if ( !$minute_finish) $minute_finish = $minute_start;
	}
	$html = $html.'<select name="'.$name.'[hour_start]"/>';
	$default = '';
	if ( $hour_start === '-' ) $default = ' selected';
	$html = $html."<option value=\"-\" $default>-";
	for ($i = 0 ; $i < 24 ; $i++) {
		$default = "";
		if ( sprintf("%02d", $i) === $hour_start ) $default = ' selected';
		$html = $html."<option value=\"$i\" $default> ".sprintf("%02d", $i);
	}
	$html = $html.'</select>';
	$html = $html.'<nbsp>:<nbsp>';
	$html = $html.'<select name="'.$name.'[minute_start]"/>';
	$default = '';
	if ( $minute_start === '-' ) $default = ' selected';
	$html = $html."<option value=\"-\" $default>-";
	for ($i = 0 ; $i < 60 ; $i++) {
		$default = "";
		if ( sprintf("%02d", $i) === $minute_start ) $default = ' selected';
		$html = $html."<option value=\"$i\" $default> ".sprintf("%02d", $i);
	}
	$html = $html.'</select>';
	$html = $html.'</td>';
	$html = $html.'</tr>';
	$html = $html.'<tr>';
	$html = $html.'<td>'._("Time to finish:").'</td>';
	$html = $html.'<td>';
	$html = $html.'<select name="'.$name.'[hour_finish]"/>';
	$default = '';
	if ( $hour_finish === '-' ) $default = ' selected';
	$html = $html."<option value=\"-\" $default>-";
	for ($i = 0 ; $i < 24 ; $i++) {
		$default = "";
		if ( sprintf("%02d", $i) === $hour_finish) $default = ' selected';
		$html = $html."<option value=\"$i\" $default> ".sprintf("%02d", $i);
	}
	$html = $html.'</select>';
	$html = $html.'<nbsp>:<nbsp>';
	$html = $html.'<select name="'.$name.'[minute_finish]"/>';
	$default = '';
	if ( $minute_finish === '-' ) $default = ' selected';
	$html = $html."<option value=\"-\" $default>-";
	for ($i = 0 ; $i < 60 ; $i++) {
		$default = '';
		if ( sprintf("%02d", $i) === $minute_finish ) $default = ' selected';
		$html = $html."<option value=\"$i\" $default> ".sprintf("%02d", $i);
	}
	$html = $html.'</select>';
	$html = $html.'</td>';
	$html = $html.'</tr>';
	$html = $html.'<tr>';
// WDay could be *, day, day1-day2
	if ( $time_wday != '*' ) {
		list($wday_start, $wday_finish) = explode('-', $time_wday);
		if ($wday_start === '*') {$wday_start = $wday_finish;}
		if ($wday_finish === '*') {$wday_finish = $wday_start;}
		if ( !$wday_finish) $wday_finish = $wday_start;
	} else {
		$wday_start = $wday_finish = '-';
	}
	$html = $html.'<td>'._("Week Day Start:").'</td>';
	$html = $html.'<td>';
	$html = $html.'<select name="'.$name.'[wday_start]"/>';
	if ( $wday_start == '-' ) { $default = ' selected'; }
		else {$default = '';}
	$html = $html."<option value=\"-\" $default>-";
 
	if ( $wday_start == 'mon' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"mon\" $default>" . _("Monday");

	if ( $wday_start == 'tue' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"tue\" $default>" . _("Tuesday");

	if ( $wday_start == 'wed' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"wed\" $default>" . _("Wednesday");

	if ( $wday_start == 'thu' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"thu\" $default>" . _("Thursday");

	if ( $wday_start == 'fri' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"fri\" $default>" . _("Friday");

	if ( $wday_start == 'sat' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"sat\" $default>" . _("Saturday");

	if ( $wday_start == 'sun' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"sun\" $default>" . _("Sunday");

	$html = $html.'</td>';
	$html = $html.'</tr>';
	$html = $html.'<tr>';
	$html = $html.'<td>'._("Week Day finish:").'</td>';
	$html = $html.'<td>';
	$html = $html.'<select name="'.$name.'[wday_finish]"/>';

	if ( $wday_finish == '-' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"-\" $default>-";
 
	if ( $wday_finish == 'mon' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"mon\" $default>" . _("Monday");

	if ( $wday_finish == 'tue' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"tue\" $default>" . _("Tuesday");

	if ( $wday_finish == 'wed' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"wed\" $default>" . _("Wednesday");

	if ( $wday_finish == 'thu' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"thu\" $default>" . _("Thursday");

	if ( $wday_finish == 'fri' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"fri\" $default>" . _("Friday");

	if ( $wday_finish == 'sat' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"sat\" $default>" . _("Saturday");

	if ( $wday_finish == 'sun' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"sun\" $default>" . _("Sunday");

	$html = $html.'</td>';
	$html = $html.'</tr>';
	$html = $html.'<tr>';
	$html = $html.'<td>'._("Month Day start:").'</td>';

// MDay could be *, day, day1-day2
	if ( $time_mday != '*' ) {
		list($mday_start, $mday_finish) = explode('-', $time_mday);
		if ($mday_start === '*') {$mday_start = $mday_finish;}
		if ($mday_finish === '*') {$mday_finish = $mday_start;}
		if ( !$mday_finish) $mday_finish = $mday_start;
	} else {
		$mday_start = $mday_finish = '-';
	}

	$html = $html.'<td>';
	$html = $html.'<select name="'.$name.'[mday_start]"/>';
	$default = '';
	if ( $mday_start == '-' ) $default = ' selected';
	$html = $html."<option value=\"-\" $default>-";
	for ($i = 1 ; $i < 32 ; $i++) {
		$default = '';
		if ( $i == $mday_start ) $default = ' selected';
		$html = $html."<option value=\"$i\" $default> $i";
	}
	$html = $html.'</select>';
	$html = $html.'</td>';
	$html = $html.'<tr>';
	$html = $html.'<td>'._("Month Day finish:").'</td>';
	$html = $html.'<td>';
	$html = $html.'<select name="'.$name.'[mday_finish]"/>';
	$default = '';
	if ( $mday_finish == '-' ) $default = ' selected';
	$html = $html."<option value=\"-\" $default>-";
	for ($i = 1 ; $i < 32 ; $i++) {
		$default = '';
		if ( $i == $mday_finish ) $default = ' selected';
		$html = $html."<option value=\"$i\" $default> $i";
	}
	$html = $html.'</select>';
	$html = $html.'</td>';
	$html = $html.'</tr>';
	$html = $html.'<tr>';
	$html = $html.'<td>'._("Month start:").'</td>';

// Month could be *, month, month1-month2
	if ( $time_month != '*' ) {
		list($month_start, $month_finish) = explode('-', $time_month);
		if ($month_start === '*') {$month_start = $month_finish;}
		if ($month_finish === '*') {$month_finish = $month_start;}
		if ( !$month_finish) $month_finish = $month_start;
	} else {
		$month_start = $month_finish = '-';
	}
	$html = $html.'<td>';
	$html = $html.'<select name="'.$name.'[month_start]"/>';

	if ( $month_start == '-' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"-\" $default>-";

	if ( $month_start == 'jan' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"jan\" $default>" . _("January");
	                               
	if ( $month_start == 'feb' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"feb\" $default>" . _("February");
	
	if ( $month_start == 'mar' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"mar\" $default>" . _("March");
	                               
	if ( $month_start == 'apr' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"apr\" $default>" . _("April");
	 
	if ( $month_start == 'may' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"may\" $default>" . _("May");
                               
	if ( $month_start == 'jun' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"jun\" $default>" . _("June");

	if ( $month_start == 'jul' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"jul\" $default>" . _("July");
	                               
	if ( $month_start == 'aug' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"aug\" $default>" . _("August");
	 
	if ( $month_start == 'sep' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"sep\" $default>" . _("September");
                               
	if ( $month_start == 'oct' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"oct\" $default>" . _("October");

	if ( $month_start == 'nov' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"nov\" $default>" . _("November");
	                               
	if ( $month_start == 'dec' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"dec\" $default>" . _("December");

	$html = $html.'</select>';
	$html = $html.'</td>';
	$html = $html.'</tr>';
	$html = $html.'<tr>';
	$html = $html.'<td>'._("Month finish:").'</td>';
	$html = $html.'<td>';
	$html = $html.'<select name="'.$name.'[month_finish]"/>';

	if ( $month_finish == '-' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"-\" $default>-";

	if ( $month_finish == 'jan' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"jan\" $default>" . _("January");
	                               
	if ( $month_finish == 'feb' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"feb\" $default>" . _("February");
	
	if ( $month_finish == 'mar' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"mar\" $default>" . _("March");
	                               
	if ( $month_finish == 'apr' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"apr\" $default>" . _("April");
	 
	if ( $month_finish == 'may' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"may\" $default>" . _("May");
	                               
	if ( $month_finish == 'jun' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"jun\" $default>" . _("June");
	
	if ( $month_finish == 'jul' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"jul\" $default>" . _("July");
	                               
	if ( $month_finish == 'aug' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"aug\" $default>" . _("August");
	 
	if ( $month_finish == 'sep' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"sep\" $default>" . _("September");
	                               
	if ( $month_finish == 'oct' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"oct\" $default>" . _("October");
	
	if ( $month_finish == 'nov' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"nov\" $default>" . _("November");
	                               
	if ( $month_finish == 'dec' ) { $default = ' selected'; }
	else {$default = '';}
	$html = $html."<option value=\"dec\" $default>" . _("December");

	$html = $html.'</select>';
	$html = $html.'</td>';
	$html = $html.'</tr>';
	$html = $html.'</tr>';
	return $html;
}

function customcontexts_customcontextstimes_buildtime( $hour_start, $minute_start, $hour_finish, $minute_finish, $wday_start, $wday_finish, $mday_start, $mday_finish, $month_start, $month_finish) {

        //----- Time Hour Interval proccess ----
        if ($minute_start == '-') {
            $time_minute_start = "00";
         } else {
            $time_minute_start = sprintf("%02d",$minute_start);
         }
         if ($minute_finish == '-') {
            $time_minute_finish = "00";
         } else {
             $time_minute_finish = sprintf("%02d",$minute_finish);
         }
         if ($hour_start == '-') {
             $time_hour_start = '*';
          } else {
             $time_hour_start = sprintf("%02d",$hour_start) . ':' . $time_minute_start;
          }
          if ($hour_finish == '-') {
             $time_hour_finish = '*';
          } else {
             $time_hour_finish = sprintf("%02d",$hour_finish) . ':' . $time_minute_finish;
          }
          if ($time_hour_start === '*') {$time_hour_start = $time_hour_finish;}
          if ($time_hour_finish === '*') {$time_hour_finish = $time_hour_start;}
          if ($time_hour_start == $time_hour_finish) {
              $time_hour = $time_hour_start;
          } else {
              $time_hour = $time_hour_start . '-' . $time_hour_finish;
          }
          //----- Time Week Day Interval proccess -----
          if ($wday_start == '-') {
              $time_wday_start = '*';
           } else {
              $time_wday_start = $wday_start;
           }
           if ($wday_finish == '-') {
              $time_wday_finish = '*';
           } else {
              $time_wday_finish = $wday_finish;
           }
           if ($time_wday_start === '*') {$time_wday_start = $time_wday_finish;}
           if ($time_wday_finish === '*') {$time_wday_finish = $time_wday_start;}
           if ($time_wday_start == $time_wday_finish) {
               $time_wday = $time_wday_start;
            } else {
               $time_wday = $time_wday_start . '-' . $time_wday_finish;
            }
            //----- Time Month Day Interval proccess -----
            if ($mday_start == '-') {
               $time_mday_start = '*';
            } else {
                $time_mday_start = $mday_start;
            }
            if ($mday_finish == '-') {
                $time_mday_finish = '*';
            } else {
                $time_mday_finish = $mday_finish;
            }
            if ($time_mday_start === '*') {$time_mday_start = $time_mday_finish;}
            if ($time_mday_finish === '*') {$time_mday_finish = $time_mday_start;}
            if ($time_mday_start == $time_mday_finish) {
                $time_mday = $time_mday_start;
            } else {
                $time_mday = $time_mday_start . '-' . $time_mday_finish;
            }
            //----- Time Month Interval proccess -----
            if ($month_start == '-') {
                $time_month_start = '*';
            } else {
                $time_month_start = $month_start;
            }
            if ($month_finish == '-') {
                $time_month_finish = '*';
            } else {
                $time_month_finish = $month_finish;
            }
            if ($time_month_start === '*') {$time_month_start = $time_month_finish;}
            if ($time_month_finish === '*') {$time_month_finish = $time_month_start;}
            if ($time_month_start == $time_month_finish) {
                $time_month = $time_month_start;
            } else {
                $time_month = $time_month_start . '-' . $time_month_finish;
            }
	    $time = $time_hour . '|' . $time_wday . '|' . $time_mday . '|' . $time_month;
	    return $time;
}



//---------------------------end stolen from timeconditions-------------------------------------


?>
