<?php

/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*	$Id: Admin_Controller.php 11256 2011-10-25 13:52:18Z c_schmitz $
*/

/**
* Survey Common Action
*
* This controller contains common functions for survey related views.
*
* @package		LimeSurvey
* @subpackage	Backend
* @author		Shitiz Garg
*/
class Survey_Common_Action extends CAction
{

    /**
    * Override runWithParams() implementation in CAction to help us parse
    * requests with subactions.
    *
    * @param array $params URL Parameters
    */
    public function runWithParams($params)
    {
        // Default method that would be called if the subaction and run() do not exist
        $sDefault = 'index';

        // Check for a subaction
        if (empty($params['sa']))
        {
            $sSubAction = $sDefault; // default
        }
        else
        {
            $sSubAction = $params['sa'];
        }

        // Check if the class has the method
        $oClass = new ReflectionClass($this);
        if (!$oClass->hasMethod($sSubAction))
        {
            // If it doesn't, revert to default Yii method, that is run() which should reroute us somewhere else
            $sSubAction = 'run';
        }

        // Populate the params. eg. surveyid -> iSurveyId
        $params = $this->_addPseudoParams($params);

        // Check if the method is public and of the action class, not its parents
        // ReflectionClass gets us the methods of the class and parent class
        // If the above method existence check passed, it might not be neceessary that it is of the action class
        $oMethod  = new ReflectionMethod($this, $sSubAction);

        // Get the action classes from the admin controller as the urls necessarily do not equal the class names. Eg. survey -> surveyaction
        $aActions = Yii::app()->getController()->getActionClasses();
        if(empty($aActions[$this->getId()]) || strtolower($oMethod->getDeclaringClass()->name) != $aActions[$this->getId()] || !$oMethod->isPublic())
        {
            // Either action doesn't exist in our whitelist, or the method class doesn't equal the action class or the method isn't public
            // So let us get the last possible default method, ie. index
            $oMethod = new ReflectionMethod($this, $sDefault);
        }

        // We're all good to go, let's execute it
        // runWithParamsInternal would automatically get the parameters of the method and populate them as required with the params
        return parent::runWithParamsInternal($this, $oMethod, $params);
    }

    /**
    * Some functions have different parameters, which are just an alias of the
    * usual parameters we're getting in the url. This function just populates
    * those variables so that we don't end up in an error.
    *
    * This is also used while rendering wrapped template
    * {@link Survey_Common_Action::_renderWrappedTemplate()}
    *
    * @param array $params Parameters to parse and populate
    * @return array Populated parameters
    */
    private function _addPseudoParams($params)
    {
        // Return if params isn't an array
        if (empty($params) || !is_array($params))
        {
            return $params;
        }

        $pseudos = array(
            'id' => 'iId',
            'gid' => 'iGroupId',
            'qid' => 'iQuestionId',
            'sid' => 'iSurveyId',
            'surveyid' => 'iSurveyId',
            'srid' => 'iSurveyResponseId',
            'scid' => 'iSavedControlId',
            'uid' => 'iUserId',
            'ugid' => 'iUserGroupId',
            'fieldname' => 'sFieldName',
            'fieldtext' => 'sFieldText',
            'action' => 'sAction',
            'lang' => 'sLanguage',
            'browselang' => 'sBrowseLang',
            'tokenids' => 'aTokenIds',
            'tokenid' => 'iTokenId',
            'subaction' => 'sSubAction',
        );

        // Foreach pseudo, take the key, if it exists,
        // Populate the values (taken as an array) as keys in params
        // with that key's value in the params
        // (only if that place is empty)
        foreach ($pseudos as $key => $pseudo)
        {
            if (!empty($params[$key]))
            {
                $pseudo = (array) $pseudo;

                foreach ($pseudo as $pseud)
                {
                    if (empty($params[$pseud]))
                    {
                        $params[$pseud] = $params[$key];
                    }
                }
            }
        }

        // Finally return the populated array
        return $params;
    }

    /**
    * Action classes require them to have a run method. We reroute it to index
    * if called.
    */
    public function run()
    {
        $this->index();
    }

    /**
    * Routes the action into correct subaction
    *
    * @access protected
    * @param string $sa
    * @param array $get_vars
    * @return void
    */
    protected function route($sa, array $get_vars)
    {
        $func_args = array();
        foreach ($get_vars as $k => $var)
            $func_args[$k] = Yii::app()->request->getQuery($var);

        return call_user_func_array(array($this, $sa), $func_args);
    }

    /**
    * Renders template(s) wrapped in header and footer
    *
    * @param string $sAction Current action, the folder to fetch views from
    * @param string|array $aViewUrls View url(s)
    * @param array $aData Data to be passed on. Optional.
    */
    protected function _renderWrappedTemplate($sAction = '', $aViewUrls = array(), $aData = array(), $getHeader = true)
    {
        // Gather the data
        $aData['clang'] = $clang = Yii::app()->lang;
        $aData = $this->_addPseudoParams($aData);
        $aViewUrls = (array) $aViewUrls;
        $sViewPath = '/admin/';

        if (!empty($sAction))
        {
            $sViewPath .= $sAction . '/';
        }

        // Header
        if($getHeader)
            Yii::app()->getController()->_getAdminHeader();

        // Menu bars
        if (!isset($aData['display']['menu_bars']) || ($aData['display']['menu_bars'] !== false && (!is_array($aData['display']['menu_bars']) || !in_array('browse', array_keys($aData['display']['menu_bars'])))))
        {
            Yii::app()->getController()->_showadminmenu(!empty($aData['surveyid']) ? $aData['surveyid'] : null);

            if (!empty($aData['surveyid']))
            {
                $this->_surveybar($aData['surveyid'], !empty($aData['gid']) ? $aData['gid'] : null);

                if (isset($aData['display']['menu_bars']['surveysummary']))
                {

                    if ((empty($aData['display']['menu_bars']['surveysummary']) || !is_string($aData['display']['menu_bars']['surveysummary'])) && !empty($aData['gid']))
                    {
                        $aData['display']['menu_bars']['surveysummary'] = 'viewgroup';
                    }
                    $this->_surveysummary($aData['surveyid'], !empty($aData['display']['menu_bars']['surveysummary']) ? $aData['display']['menu_bars']['surveysummary'] : null, !empty($aData['gid']) ? $aData['gid'] : null);
                }

                if (!empty($aData['gid']))
                {
                    if (empty($aData['display']['menu_bars']['gid_action']) && !empty($aData['qid']))
                    {
                        $aData['display']['menu_bars']['gid_action'] = 'viewquestion';
                    }

                    $this->_questiongroupbar($aData['surveyid'], $aData['gid'], !empty($aData['qid']) ? $aData['qid'] : null, !empty($aData['display']['menu_bars']['gid_action']) ? $aData['display']['menu_bars']['gid_action'] : null);

                    if (!empty($aData['qid']))
                    {
                        $this->_questionbar($aData['surveyid'], $aData['gid'], $aData['qid'], !empty($aData['display']['menu_bars']['qid_action']) ? $aData['display']['menu_bars']['qid_action'] : null);
                    }
                }
            }
        }

        if (!empty($aData['display']['menu_bars']['browse']) && !empty($aData['surveyid']))
        {
            $this->_browsemenubar($aData['surveyid'], $aData['display']['menu_bars']['browse']);
        }

        if (!empty($aData['display']['menu_bars']['user_group']))
        {
            $this->_userGroupBar(!empty($aData['ugid']) ? $aData['ugid'] : 0);
        }

        unset($aData['display']);

        // Load views
        foreach ($aViewUrls as $sViewKey => $viewUrl)
        {
            if (empty($sViewKey) || !in_array($sViewKey, array('message', 'output')))
            {
                if (is_numeric($sViewKey))
                {
                    Yii::app()->getController()->render($sViewPath . $viewUrl, $aData);
                }
                elseif (is_array($viewUrl))
                {
                    foreach ($viewUrl as $aSubData)
                    {
                        $aSubData = array_merge($aData, $aSubData);
                        Yii::app()->getController()->render($sViewPath . $sViewKey, $aSubData);
                    }
                }
            }
            else
            {
                switch ($sViewKey)
                {
                    // Message
                    case 'message' :
                        if (empty($viewUrl['class']))
                        {
                            Yii::app()->getController()->_showMessageBox($viewUrl['title'], $viewUrl['message']);
                        }
                        else
                        {
                            Yii::app()->getController()->_showMessageBox($viewUrl['title'], $viewUrl['message'], $viewUrl['class']);
                        }
                        break;

                        // Output
                    case 'output' :
                        echo $viewUrl;
                        break;
                }
            }
        }

        // Footer
        Yii::app()->getController()->_loadEndScripts();
        Yii::app()->getController()->_getAdminFooter('http://docs.limesurvey.org', $clang->gT('LimeSurvey online manual'));
    }

    /**
    * Shows admin menu for question
    * @param int Survey id
    * @param int Group id
    * @param int Question id
    * @param string action
    */
    function _questionbar($iSurveyId, $gid, $qid, $action = null)
    {
        $clang = $this->getController()->lang;


        $baselang = Survey::model()->findByPk($iSurveyId)->language;

        //Show Question Details
        //Count answer-options for this question
        $qrr = Answers::model()->findAllByAttributes(array('qid' => $qid, 'language' => $baselang));

        $aData['qct'] = $qct = count($qrr);

        //Count sub-questions for this question
        $sqrq = Questions::model()->findAllByAttributes(array('qid' => $qid, 'language' => $baselang));
        $aData['sqct'] = $sqct = count($sqrq);

        $qrresult = Questions::model()->findAllByAttributes(array('qid' => $qid, 'gid' => $gid, 'sid' => $iSurveyId, 'language' => $baselang));

        $questionsummary = "<div class='menubar'>\n";

        // Check if other questions in the Survey are dependent upon this question
        $condarray = GetQuestDepsForConditions($iSurveyId, "all", "all", $qid, "by-targqid", "outsidegroup");

        $sumresult1 = Survey::model()->findByPk($iSurveyId);
        if (is_null($sumresult1))
        {
            die('Invalid survey id');
        } //  if surveyid is invalid then die to prevent errors at a later time
        $surveyinfo = $sumresult1->attributes;

        $surveyinfo = array_map('FlattenText', $surveyinfo);
        $aData['activated'] = $surveyinfo['active'];

        foreach ($qrresult as $qrrow)
        {
            $qrrow = $qrrow->attributes;
            $qrrow = array_map('FlattenText', $qrrow);
            if (bHasSurveyPermission($iSurveyId, 'surveycontent', 'read'))
            {
                if (count(Survey::model()->findByPk($iSurveyId)->additionalLanguages) == 0)
                {

                }
                else
                {
                    Yii::app()->loadHelper('surveytranslator');
                    $tmp_survlangs = Survey::model()->findByPk($iSurveyId)->additionalLanguages;
                    $baselang = Survey::model()->findByPk($iSurveyId)->language;
                    $tmp_survlangs[] = $baselang;
                    rsort($tmp_survlangs);
                    $aData['tmp_survlangs'] = $tmp_survlangs;
                }
            }
            $aData['qtypes'] = $qtypes = getqtypelist('', 'array');
            if ($action == 'editansweroptions' || $action == "editsubquestions" || $action == "editquestion" || $action == "editdefaultvalues" || $action == "copyquestion")
            {
                $qshowstyle = "style='display: none'";
            }
            else
            {
                $qshowstyle = "";
            }
            $aData['qshowstyle'] = $qshowstyle;
            $aData['action'] = $action;
            $aData['surveyid'] = $iSurveyId;
            $aData['qid'] = $qid;
            $aData['gid'] = $gid;
            $aData['clang'] = $clang;
            $aData['qrrow'] = $qrrow;
            $aData['baselang'] = $baselang;
            $aAttributesWithValues = Questions::model()->getAdvancedSettingsWithValues($qid, $qrrow['type'], $iSurveyId, $baselang);
            $DisplayArray = array();
            foreach ($aAttributesWithValues as $aAttribute)
            {
                if (($aAttribute['i18n'] == false && isset($aAttribute['value']) && $aAttribute['value'] != $aAttribute['default']) || ($aAttribute['i18n'] == true && isset($aAttribute['value'][$baselang]) && $aAttribute['value'][$baselang] != $aAttribute['default']))
                {
                    if ($aAttribute['inputtype'] == 'singleselect')
                    {
                        $aAttribute['value'] = $aAttribute['options'][$aAttribute['value']];
                    }
                    /*
                    if ($aAttribute['name']=='relevance')
                    {
                    $sRelevance = $aAttribute['value'];
                    if ($sRelevance !== '' && $sRelevance !== '1' && $sRelevance !== '0')
                    {
                    LimeExpressionManager::ProcessString("{" . $sRelevance . "}");    // tests Relevance equation so can pretty-print it
                    $aAttribute['value']= LimeExpressionManager::GetLastPrettyPrintExpression();
                    }
                    }
                    */
                    $DisplayArray[] = $aAttribute;
                }
            }
            if (is_null($qrrow['relevance']) || trim($qrrow['relevance']) == '')
            {
                $aData['relevance'] = 1;
            }
            else
            {
                LimeExpressionManager::ProcessString("{" . $qrrow['relevance'] . "}", $aData['qid']);    // tests Relevance equation so can pretty-print it
                $aData['relevance'] = LimeExpressionManager::GetLastPrettyPrintExpression();
            }
            $aData['advancedsettings'] = $DisplayArray;
            $aData['condarray'] = $condarray;
            $questionsummary .= $this->getController()->render("/admin/survey/Question/questionbar_view", $aData, true);
        }
        $finaldata['display'] = $questionsummary;

        $this->getController()->render('/survey_view', $finaldata);
    }

    /**
    * Shows admin menu for question groups
    * @param int Survey id
    * @param int Group id
    */
    function _questiongroupbar($iSurveyId, $gid, $qid=null, $action = null)
    {
        $clang = $this->getController()->lang;
        $baselang = Survey::model()->findByPk($iSurveyId)->language;

        Yii::app()->loadHelper('replacements');
        // TODO: check that surveyid and thus baselang are always set here
        $sumresult4 = Questions::model()->findAllByAttributes(array('sid' => $iSurveyId, 'gid' => $gid, 'language' => $baselang));
        $sumcount4 = count($sumresult4);

        $grpresult = Groups::model()->findAllByAttributes(array('gid' => $gid, 'language' => $baselang));

        // Check if other questions/groups are dependent upon this group
        $condarray = GetGroupDepsForConditions($iSurveyId, "all", $gid, "by-targgid");

        $groupsummary = "<div class='menubar'>\n"
        . "<div class='menubar-title ui-widget-header'>\n";

        //$sumquery1 = "SELECT * FROM ".db_table_name('surveys')." inner join ".db_table_name('surveys_languagesettings')." on (surveyls_survey_id=sid and surveyls_language=language) WHERE sid=$iSurveyId"; //Getting data for this survey
        $sumresult1 = Survey::model()->with('languagesettings')->findByPk($iSurveyId); //$sumquery1, 1) ; //Checked //  if surveyid is invalid then die to prevent errors at a later time
        $surveyinfo = $sumresult1->attributes;
        $surveyinfo = array_merge($surveyinfo, $sumresult1->languagesettings->attributes);
        $surveyinfo = array_map('FlattenText', $surveyinfo);
        //$surveyinfo = array_map('htmlspecialchars', $surveyinfo);
        $aData['activated'] = $activated = $surveyinfo['active'];

        foreach ($grpresult as $grow)
        {
            $grow = $grow->attributes;

            $grow = array_map('FlattenText', $grow);
            $aData = array();
            $aData['activated'] = $activated;
            $aData['qid'] = $qid;
            $aData['QidPrev'] = $QidPrev = getQidPrevious($iSurveyId, $gid, $qid);
            $aData['QidNext'] = $QidNext = getQidNext($iSurveyId, $gid, $qid);

            if ($action == 'editgroup' || $action == 'addquestion' || $action == 'viewquestion' || $action == "editdefaultvalues")
            {
                $gshowstyle = "style='display: none'";
            }
            else
            {
                $gshowstyle = "";
            }

            $aData['gshowstyle'] = $gshowstyle;
            $aData['surveyid'] = $iSurveyId;
            $aData['gid'] = $gid;
            $aData['grow'] = $grow;
            $aData['clang'] = $clang;
            $aData['condarray'] = $condarray;
            $aData['sumcount4'] = $sumcount4;

            $groupsummary .= $this->getController()->render('/admin/survey/QuestionGroups/questiongroupbar_view', $aData, true);
        }
        $groupsummary .= "\n</table>\n";

        $finaldata['display'] = $groupsummary;
        $this->getController()->render('/survey_view', $finaldata);
    }

    /**
    * Shows admin menu for surveys
    * @param int Survey id
    */
    function _surveybar($iSurveyId, $gid=null)
    {
        //$this->load->helper('surveytranslator');
        $clang = $this->getController()->lang;
        //echo Yii::app()->getConfig('gid');
        $baselang = Survey::model()->findByPk($iSurveyId)->language;
        $condition = array('sid' => $iSurveyId, 'language' => $baselang);

        //$sumquery1 = "SELECT * FROM ".db_table_name('surveys')." inner join ".db_table_name('surveys_languagesettings')." on (surveyls_survey_id=sid and surveyls_language=language) WHERE sid=$iSurveyId"; //Getting data for this survey
        $sumresult1 = Survey::model()->with('languagesettings')->findByPk($iSurveyId); //$sumquery1, 1) ; //Checked
        if (is_null($sumresult1))
        {
            die('Invalid survey id');
        } //  if surveyid is invalid then die to prevent errors at a later time
        $surveyinfo = $sumresult1->attributes;
        $surveyinfo = array_merge($surveyinfo, $sumresult1->languagesettings->attributes);
        $surveyinfo = array_map('FlattenText', $surveyinfo);
        //$surveyinfo = array_map('htmlspecialchars', $surveyinfo);
        $activated = ($surveyinfo['active'] == 'Y');

        $js_admin_includes[] = Yii::app()->getConfig('generalscripts') . 'jquery/jquery.coookie.js';
        $js_admin_includes[] = Yii::app()->getConfig('generalscripts') . 'jquery/superfish.js';
        $js_admin_includes[] = Yii::app()->getConfig('generalscripts') . 'jquery/hoverIntent.js';
        $js_admin_includes[] = Yii::app()->getConfig('adminscripts') . 'surveytoolbar.js';
        $this->getController()->_js_admin_includes($js_admin_includes);

        //Parse data to send to view
        $aData['clang'] = $clang;
        $aData['surveyinfo'] = $surveyinfo;
        $aData['surveyid'] = $iSurveyId;

        // ACTIVATE SURVEY BUTTON
        $aData['activated'] = $activated;
        $aData['imageurl'] = Yii::app()->getConfig('imageurl');

        $condition = array('sid' => $iSurveyId, 'parent_qid' => 0, 'language' => $baselang);

        //$sumquery3 =  "SELECT * FROM ".db_table_name('questions')." WHERE sid={$iSurveyId} AND parent_qid=0 AND language='".$baselang."'"; //Getting a count of questions for this survey
        $sumresult3 = Questions::model()->findAllByAttributes($condition); //Checked
        $sumcount3 = count($sumresult3);

        $aData['canactivate'] = $sumcount3 > 0 && bHasSurveyPermission($iSurveyId, 'surveyactivation', 'update');
        $aData['candeactivate'] = bHasSurveyPermission($iSurveyId, 'surveyactivation', 'update');
        $aData['expired'] = $surveyinfo['expires'] != '' && ($surveyinfo['expires'] < date_shift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig('timeadjust')));
        $aData['notstarted'] = ($surveyinfo['startdate'] != '') && ($surveyinfo['startdate'] > date_shift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig('timeadjust')));

        // Start of suckerfish menu
        // TEST BUTTON
        if (!$activated)
        {
            $aData['icontext'] = $clang->gT("Test This Survey");
            $aData['icontext2'] = $clang->gTview("Test This Survey");
        }
        else
        {
            $aData['icontext'] = $clang->gT("Execute This Survey");
            $aData['icontext2'] = $clang->gTview("Execute This Survey");
        }

        $aData['baselang'] = Survey::model()->findByPk($iSurveyId)->language;
        //        DebugBreak();
        $tmp_survlangs = Survey::model()->findByPk($iSurveyId)->getAdditionalLanguages();
        $aData['onelanguage']=(count($tmp_survlangs)==0);
        $aData['additionallanguages'] = $tmp_survlangs;
        $tmp_survlangs[] = $aData['baselang'];
        rsort($tmp_survlangs);
        $aData['languagelist'] = $tmp_survlangs;

        $aData['hasadditionallanguages'] = (count($aData['additionallanguages']) > 0);

        // EDIT SURVEY TEXT ELEMENTS BUTTON
        $aData['surveylocale'] = bHasSurveyPermission($iSurveyId, 'surveylocale', 'read');
        // EDIT SURVEY SETTINGS BUTTON
        $aData['surveysettings'] = bHasSurveyPermission($iSurveyId, 'surveysettings', 'read');
        // Survey permission item
        $aData['surveysecurity'] = (Yii::app()->session['USER_RIGHT_SUPERADMIN'] == 1 || $surveyinfo['owner_id'] == Yii::app()->session['loginID']);
        // CHANGE QUESTION GROUP ORDER BUTTON
        $aData['surveycontent'] = bHasSurveyPermission($iSurveyId, 'surveycontent', 'read');
        $aData['groupsum'] = (getGroupSum($iSurveyId, $surveyinfo['language']) > 1);
        // SET SURVEY QUOTAS BUTTON
        $aData['quotas'] = bHasSurveyPermission($iSurveyId, 'quotas', 'read');
        // Assessment menu item
        $aData['assessments'] = bHasSurveyPermission($iSurveyId, 'assessments', 'read');
        // EDIT SURVEY TEXT ELEMENTS BUTTON
        // End if survey properties
        // Tools menu item
        // Delete survey item
        $aData['surveydelete'] = bHasSurveyPermission($iSurveyId, 'survey', 'delete');
        // Translate survey item
        $aData['surveytranslate'] = bHasSurveyPermission($iSurveyId, 'translations', 'read');
        // RESET SURVEY LOGIC BUTTON
        //$sumquery6 = "SELECT count(*) FROM ".db_table_name('conditions')." as c, ".db_table_name('questions')." as q WHERE c.qid = q.qid AND q.sid=$iSurveyId"; //Getting a count of conditions for this survey
        // TMSW Conditions->Relevance:  How is conditionscount used?  Should Relevance do the same?

        $query = count(Conditions::model()->findAllByAttributes(array('qid' => $iSurveyId)));
        $sumcount6 = $query; //Checked
        $aData['surveycontent'] = bHasSurveyPermission($iSurveyId, 'surveycontent', 'update');
        $aData['conditionscount'] = ($sumcount6 > 0);
        // Eport menu item
        $aData['surveyexport'] = bHasSurveyPermission($iSurveyId, 'surveycontent', 'export');
        // PRINTABLE VERSION OF SURVEY BUTTON
        // SHOW PRINTABLE AND SCANNABLE VERSION OF SURVEY BUTTON
        //browse responses menu item
        $aData['respstatsread'] = bHasSurveyPermission($iSurveyId, 'responses', 'read') || bHasSurveyPermission($iSurveyId, 'statistics', 'read') || bHasSurveyPermission($iSurveyId, 'responses', 'export');
        // Data entry screen menu item
        $aData['responsescreate'] = bHasSurveyPermission($iSurveyId, 'responses', 'create');
        $aData['responsesread'] = bHasSurveyPermission($iSurveyId, 'responses', 'read');
        // TOKEN MANAGEMENT BUTTON
        $aData['tokenmanagement'] = bHasSurveyPermission($iSurveyId, 'surveysettings', 'update') || bHasSurveyPermission($iSurveyId, 'tokens', 'read');

        $aData['gid'] = $gid; // = $this->input->post('gid');

        if (bHasSurveyPermission($iSurveyId, 'surveycontent', 'read'))
        {
            $aData['permission'] = true;
        }
        else
        {
            $aData['gid'] = $gid = null;
            $qid = null;
            $aData['permission'] = false;
        }

        if (getgrouplistlang($gid, $baselang, $iSurveyId))
        {
            $aData['groups'] = getgrouplistlang($gid, $baselang, $iSurveyId);
        }
        else
        {
            $aData['groups'] = "<option>" . $clang->gT("None") . "</option>";
        }

        $aData['GidPrev'] = $GidPrev = getGidPrevious($iSurveyId, $gid);

        $aData['GidNext'] = $GidNext = getGidNext($iSurveyId, $gid);

        $this->getController()->render("/admin/survey/surveybar_view", $aData);
    }

    /**
    * Show survey summary
    * @param int Survey id
    * @param string Action to be performed
    */
    function _surveysummary($iSurveyId, $action=null, $gid=null)
    {
        $clang = $this->getController()->lang;

        $baselang = Survey::model()->findByPk($iSurveyId)->language;
        $condition = array('sid' => $iSurveyId, 'language' => $baselang);

        $sumresult1 = Survey::model()->with('languagesettings')->findByPk($iSurveyId); //$sumquery1, 1) ; //Checked
        if (is_null($sumresult1))
        {
            die('Invalid survey id');
        } //  if surveyid is invalid then die to prevent errors at a later time
        $surveyinfo = $sumresult1->attributes;
        $surveyinfo = array_merge($surveyinfo, $sumresult1->languagesettings->attributes);
        $surveyinfo = array_map('FlattenText', $surveyinfo);
        //$surveyinfo = array_map('htmlspecialchars', $surveyinfo);
        $activated = $surveyinfo['active'];

        $condition = array('sid' => $iSurveyId, 'parent_qid' => 0, 'language' => $baselang);

        $sumresult3 = Questions::model()->findAllByAttributes($condition); //Checked
        $sumcount3 = count($sumresult3);

        $condition = array('sid' => $iSurveyId, 'language' => $baselang);

        //$sumquery2 = "SELECT * FROM ".db_table_name('groups')." WHERE sid={$iSurveyId} AND language='".$baselang."'"; //Getting a count of groups for this survey
        $sumresult2 = Groups::model()->findAllByAttributes($condition); //Checked
        $sumcount2 = count($sumresult2);

        //SURVEY SUMMARY

        $aAdditionalLanguages = Survey::model()->findByPk($iSurveyId)->additionalLanguages;
        $surveysummary2 = "";
        if ($surveyinfo['anonymized'] != "N")
        {
            $surveysummary2 .= $clang->gT("Responses to this survey are anonymized.") . "<br />";
        }
        else
        {
            $surveysummary2 .= $clang->gT("Responses to this survey are NOT anonymized.") . "<br />";
        }
        if ($surveyinfo['format'] == "S")
        {
            $surveysummary2 .= $clang->gT("It is presented question by question.") . "<br />";
        }
        elseif ($surveyinfo['format'] == "G")
        {
            $surveysummary2 .= $clang->gT("It is presented group by group.") . "<br />";
        }
        else
        {
            $surveysummary2 .= $clang->gT("It is presented on one single page.") . "<br />";
        }
        if ($surveyinfo['allowjumps'] == "Y")
        {
            if ($surveyinfo['format'] == 'A')
            {
                $surveysummary2 .= $clang->gT("No question index will be shown with this format.") . "<br />";
            }
            else
            {
                $surveysummary2 .= $clang->gT("A question index will be shown; participants will be able to jump between viewed questions.") . "<br />";
            }
        }
        if ($surveyinfo['datestamp'] == "Y")
        {
            $surveysummary2 .= $clang->gT("Responses will be date stamped.") . "<br />";
        }
        if ($surveyinfo['ipaddr'] == "Y")
        {
            $surveysummary2 .= $clang->gT("IP Addresses will be logged") . "<br />";
        }
        if ($surveyinfo['refurl'] == "Y")
        {
            $surveysummary2 .= $clang->gT("Referrer URL will be saved.") . "<br />";
        }
        if ($surveyinfo['usecookie'] == "Y")
        {
            $surveysummary2 .= $clang->gT("It uses cookies for access control.") . "<br />";
        }
        if ($surveyinfo['allowregister'] == "Y")
        {
            $surveysummary2 .= $clang->gT("If tokens are used, the public may register for this survey") . "<br />";
        }
        if ($surveyinfo['allowsave'] == "Y" && $surveyinfo['tokenanswerspersistence'] == 'N')
        {
            $surveysummary2 .= $clang->gT("Participants can save partially finished surveys") . "<br />\n";
        }
        if ($surveyinfo['emailnotificationto'] != '')
        {
            $surveysummary2 .= $clang->gT("Basic email notification is sent to:") . " {$surveyinfo['emailnotificationto']}<br />\n";
        }
        if ($surveyinfo['emailresponseto'] != '')
        {
            $surveysummary2 .= $clang->gT("Detailed email notification with response data is sent to:") . " {$surveyinfo['emailresponseto']}<br />\n";
        }

        if (bHasSurveyPermission($iSurveyId, 'surveycontent', 'update'))
        {
            $surveysummary2 .= $clang->gT("Regenerate question codes:")
            . " [<a href='#' "
            . "onclick=\"if (confirm('" . $clang->gT("Are you sure you want regenerate the question codes?", "js") . "')) { " .get2post(Yii::app()->baseUrl . "?action=renumberquestions&amp;sid=$iSurveyId&amp;style=straight") . "}\" "
            . ">" . $clang->gT("Straight") . "</a>] "
            . " [<a href='#' "
            . "onclick=\"if (confirm('" . $clang->gT("Are you sure you want regenerate the question codes?", "js") . "')) { " .get2post(Yii::app()->baseUrl . "?action=renumberquestions&amp;sid=$iSurveyId&amp;style=bygroup") . "}\" "
            . ">" . $clang->gT("By Group") . "</a>]";
        }

        $dateformatdetails = getDateFormatData(Yii::app()->session['dateformat']);
        if (trim($surveyinfo['startdate']) != '')
        {
            Yii::import('application.libraries.Date_Time_Converter');
            $datetimeobj = new Date_Time_Converter($surveyinfo['startdate'], 'Y-m-d H:i:s');
            $aData['startdate'] = $datetimeobj->convert($dateformatdetails['phpdate'] . ' H:i');
        }
        else
        {
            $aData['startdate'] = "-";
        }

        if (trim($surveyinfo['expires']) != '')
        {
            //$constructoritems = array($surveyinfo['expires'] , "Y-m-d H:i:s");
            Yii::import('application.libraries.Date_Time_Converter');
            $datetimeobj = new Date_Time_Converter($surveyinfo['expires'], 'Y-m-d H:i:s');
            //$datetimeobj = new Date_Time_Converter($surveyinfo['expires'] , "Y-m-d H:i:s");
            $aData['expdate'] = $datetimeobj->convert($dateformatdetails['phpdate'] . ' H:i');
        }
        else
        {
            $aData['expdate'] = "-";
        }

        if (!$surveyinfo['language'])
        {
            $aData['language'] = getLanguageNameFromCode($currentadminlang, false);
        }
        else
        {
            $aData['language'] = getLanguageNameFromCode($surveyinfo['language'], false);
        }

        // get the rowspan of the Additionnal languages row
        // is at least 1 even if no additionnal language is present
        $additionnalLanguagesCount = count($aAdditionalLanguages);
        $first = true;
        $aData['additionnalLanguages'] = "";
        if ($additionnalLanguagesCount == 0)
        {
            $aData['additionnalLanguages'] .= "<td>-</td>\n";
        }
        else
        {
            foreach ($aAdditionalLanguages as $langname)
            {
                if ($langname)
                {
                    if (!$first)
                    {
                        $aData['additionnalLanguages'].= "<tr><td>&nbsp;</td>";
                    }
                    $first = false;
                    $aData['additionnalLanguages'] .= "<td>" . getLanguageNameFromCode($langname, false) . "</td></tr>\n";
                }
            }
        }
        if ($first)
            $aData['additionnalLanguages'] .= "</tr>";

        if ($surveyinfo['surveyls_urldescription'] == "")
        {
            $surveyinfo['surveyls_urldescription'] = htmlspecialchars($surveyinfo['surveyls_url']);
        }

        if ($surveyinfo['surveyls_url'] != "")
        {
            $aData['endurl'] = " <a target='_blank' href=\"" . htmlspecialchars($surveyinfo['surveyls_url']) . "\" title=\"" . htmlspecialchars($surveyinfo['surveyls_url']) . "\">{$surveyinfo['surveyls_urldescription']}</a>";
        }
        else
        {
            $aData['endurl'] = "-";
        }

        $aData['sumcount3'] = $sumcount3;
        $aData['sumcount2'] = $sumcount2;

        if ($activated == "N")
        {
            $aData['activatedlang'] = $clang->gT("No");
        }
        else
        {
            $aData['activatedlang'] = $clang->gT("Yes");
        }

        $aData['activated'] = $activated;
        if ($activated == "Y")
        {
            $aData['surveydb'] = Yii::app()->db->tablePrefix . "survey_" . $iSurveyId;
        }
        $aData['warnings'] = "";
        if ($activated == "N" && $sumcount3 == 0)
        {
            $aData['warnings'] = $clang->gT("Survey cannot be activated yet.") . "<br />\n";
            if ($sumcount2 == 0 && bHasSurveyPermission($iSurveyId, 'surveycontent', 'create'))
            {
                $aData['warnings'] .= "<span class='statusentryhighlight'>[" . $clang->gT("You need to add question groups") . "]</span><br />";
            }
            if ($sumcount3 == 0 && bHasSurveyPermission($iSurveyId, 'surveycontent', 'create'))
            {
                $aData['warnings'] .= "<span class='statusentryhighlight'>[" . $clang->gT("You need to add questions") . "]</span><br />";
            }
        }
        $aData['hints'] = $surveysummary2;

        //return (array('column'=>array($columns_used,$hard_limit) , 'size' => array($length, $size_limit) ));
        //        $aData['tableusage'] = get_dbtableusage($iSurveyId);
        // ToDo: Table usage is calculated on every menu display which is too slow with bug surveys.
        // Needs to be moved to a database field and only updated if there are question/subquestions added/removed (it's currently also not functional due to the port)
        //

        $aData['tableusage'] = false;
        if ($gid || ($action !== true && in_array($action, array('deactivate', 'activate', 'surveysecurity', 'editdefaultvalues', 'editemailtemplates',
        'surveyrights', 'addsurveysecurity', 'addusergroupsurveysecurity',
        'setsurveysecurity', 'setusergroupsurveysecurity', 'delsurveysecurity',
        'editsurveysettings', 'editsurveylocalesettings', 'updatesurveysettingsandeditlocalesettings', 'addgroup', 'importgroup',
        'ordergroups', 'deletesurvey', 'resetsurveylogic',
        'importsurveyresources', 'translate', 'emailtemplates',
        'exportstructure', 'quotas', 'copysurvey', 'viewgroup', 'viewquestion'))))
        {
            $showstyle = "style='display: none'";
        }
        else
        {
            $showstyle = "";
        }

        $aData['showstyle'] = $showstyle;
        $aData['aAdditionalLanguages'] = $aAdditionalLanguages;
        $aData['clang'] = $clang;
        $aData['surveyinfo'] = $surveyinfo;
        $this->getController()->render("/admin/survey/surveySummary_view", $aData);
    }

    /**
    * Browse Menu Bar
    */
    function _browsemenubar($iSurveyId, $title='')
    {
        //BROWSE MENU BAR
        $aData['title'] = $title;
        $aData['thissurvey'] = getSurveyInfo($iSurveyId);
        $aData['imageurl'] = Yii::app()->getConfig("imageurl");
        $aData['clang'] = Yii::app()->lang;
        $aData['surveyid'] = $iSurveyId;

        $tmp_survlangs = Survey::model()->findByPk($iSurveyId)->additionalLanguages;
        $baselang = Survey::model()->findByPk($iSurveyId)->language;
        $tmp_survlangs[] = $baselang;
        rsort($tmp_survlangs);
        $aData['tmp_survlangs'] = $tmp_survlangs;

        $this->getController()->render("/admin/browse/browsemenubar_view", $aData);
    }
    /**
    * Load menu bar of user group controller.
    * @param int $ugid
    * @return void
    */
    function _userGroupBar($ugid = 0)
    {
        $data['clang'] = Yii::app()->lang;
        Yii::app()->loadHelper('database');

        if (!empty($ugid)) {
            $grpquery = "SELECT gp.* FROM {{user_groups}} AS gp, {{user_in_groups}} AS gu WHERE gp.ugid=gu.ugid AND gp.ugid = $ugid AND gu.uid=" . Yii::app()->session['loginID'];
            $grpresult = db_execute_assoc($grpquery);
            $grpresultcount = db_records_count($grpquery);

            if ($grpresultcount > 0) {
                $grow = array_map('htmlspecialchars', $grpresult->read());
            }
            else
            {
                $grow = false;
            }

            $data['grow'] = $grow;
            $data['grpresultcount'] = $grpresultcount;

        }

        $data['ugid'] = $ugid;

        $this->getController()->render('/admin/usergroup/usergroupbar_view', $data);
    }

    protected function _filterImportedResources($extractdir, $destdir)
    {
        $clang = $this->getController()->lang;
        $dh = opendir($extractdir);
        $aErrorFilesInfo = array();
        $aImportedFilesInfo = array();

        if (!is_dir($destdir))
            mkdir($destdir);

        while ($direntry = readdir($dh))
        {
            if ($direntry != "." && $direntry != "..")
            {
                if (is_file($extractdir . "/" . $direntry))
                {
                    // is  a file
                    $extfile = substr(strrchr($direntry, '.'), 1);
                    if (!(stripos(',' . Yii::app()->getConfig('allowedresourcesuploads') . ',', ',' . $extfile . ',') === false))
                    {
                        // Extension allowed
                        if (!copy($extractdir . "/" . $direntry, $destdir . "/" . $direntry))
                        {
                            $aErrorFilesInfo[] = Array(
                                "filename" => $direntry,
                                "status" => $clang->gT("Copy failed")
                            );
                        }
                        else
                        {
                            $aImportedFilesInfo[] = Array(
                                "filename" => $direntry,
                                "status" => $clang->gT("OK")
                            );
                        }
                    }
                    else
                    {
                        // Extension forbidden
                        $aErrorFilesInfo[] = Array(
                            "filename" => $direntry,
                            "status" => $clang->gT("Forbidden Extension")
                        );
                    }
                    unlink($extractdir . "/" . $direntry);
                }
                elseif (is_dir($extractdir . "/" . $direntry))
                {
                    list($_aImportedFilesInfo, $_aErrorFilesInfo) = $this->_filterImportedResources($extractdir . "/" . $direntry, $destdir . "/" . $direntry);
                    $aImportedFilesInfo = array_merge($aImportedFilesInfo, $_aImportedFilesInfo);
                    $aErrorFilesInfo = array_merge($aErrorFilesInfo, $_aErrorFilesInfo);
                }
            }
        }

        rmdir($extractdir);

        return array($aImportedFilesInfo, $aErrorFilesInfo);
    }

}
