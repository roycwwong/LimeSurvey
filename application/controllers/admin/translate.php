<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
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
 */
/**
* Translate Controller
*
* This controller performs translation actions
*
* @package		LimeSurvey
* @subpackage	Backend
*/
class translate extends Survey_Common_Action {

    public function index()
    {
        $iSurveyID = sanitize_int($_REQUEST['surveyid']);
        $tolang = Yii::app()->getRequest()->getParam('lang');
        $action = Yii::app()->getRequest()->getParam('action');
		$actionvalue = Yii::app()->getRequest()->getPost('actionvalue');
        //echo $this->query('title','querybase');
        //die();

        if ( $action == "ajaxtranslategoogleapi" )
        {
            echo $this->translate_google_api();
            return;
        }

        App()->getClientScript()->registerScriptFile(Yii::app()->getConfig("adminscripts") . 'translation.js');

        $clang = Yii::app()->lang;
        $baselang = Survey::model()->findByPk($iSurveyID)->language;
        $langs = Survey::model()->findByPk($iSurveyID)->additionalLanguages;

        Yii::app()->loadHelper("database");
		Yii::app()->loadHelper("admin/htmleditor");

        if ( empty($tolang) && count($langs) > 0 )
        {
            $tolang = $langs[0];
        }

        // TODO need to do some validation here on surveyid
        $surveyinfo = getSurveyInfo($iSurveyID);
        $survey_title = $surveyinfo['name'];

        Yii::app()->loadHelper("surveytranslator");
        $supportedLanguages = getLanguageData(FALSE,Yii::app()->session['adminlang']);

        $baselangdesc = $supportedLanguages[$baselang]['description'];

        $aData = array(
			"surveyid" => $iSurveyID,
			"survey_title" => $survey_title,
			"tolang" => $tolang,
			"clang" => $clang,
			"adminmenu" => $this->showTranslateAdminmenu($iSurveyID, $survey_title, $tolang)
		);
        $aViewUrls['translateheader_view'][] = $aData;

        $tab_names = array("title", "welcome", "group", "question", "subquestion", "answer",
						"emailinvite", "emailreminder", "emailconfirmation", "emailregistration");

        if ( ! empty($tolang) )
        {
            // Only save if the administration user has the correct permission
			if ( $actionvalue == "translateSave" && Permission::model()->hasSurveyPermission($iSurveyID, 'translations', 'update') )
			{
				$this->_translateSave($iSurveyID, $tolang, $baselang, $tab_names);
			}

            $tolangdesc = $supportedLanguages[$tolang]['description'];
			// Display tabs with fields to translate, as well as input fields for translated values
			$aViewUrls = array_merge($aViewUrls, $this->_displayUntranslatedFields($iSurveyID, $tolang, $baselang, $tab_names, $baselangdesc, $tolangdesc));
            //var_dump(array_keys($aViewUrls));die();
        }

        $this->_renderWrappedTemplate('translate', $aViewUrls, $aData);
    }

	private function _translateSave($iSurveyID, $tolang, $baselang, $tab_names)
	{
		$tab_names_full = $tab_names;

		foreach( $tab_names as $type )
		{
			$amTypeOptions = $this->setupTranslateFields($type);
			$type2 = $amTypeOptions["associated"];

			if ( ! empty($type2) ) $tab_names_full[] = $type2;
		}

		foreach( $tab_names_full as $type )
		{
			$size = (int) Yii::app()->getRequest()->getPost("{$type}_size");
			// start a loop in order to update each record
			$i = 0;
			while ($i <= $size)
			{
				// define each variable
				if ( Yii::app()->getRequest()->getPost("{$type}_newvalue_{$i}") )
				{
					$old = Yii::app()->getRequest()->getPost("{$type}_oldvalue_{$i}");
					$new = Yii::app()->getRequest()->getPost("{$type}_newvalue_{$i}");

					// check if the new value is different from old, and then update database
					if ( $new != $old )
					{
						$id1 = Yii::app()->getRequest()->getPost("{$type}_id1_{$i}");
						$id2 = Yii::app()->getRequest()->getPost("{$type}_id2_{$i}");

						$this->query($type, 'queryupdate', $iSurveyID, $tolang, $baselang, $id1, $id2, $new);
					}
				}
				$i++;
			} // end while
		} // end foreach
	}

	private function _displayUntranslatedFields($iSurveyID, $tolang, $baselang, $tab_names, $baselangdesc, $tolangdesc)
	{
		$aData['surveyid'] = $iSurveyID;
		$aData['clang'] = Yii::app()->lang;
		$aData['tab_names'] = $tab_names;
		$aData['tolang'] = $tolang;
		$aData['baselang'] = $baselang;

		foreach( $tab_names as $type )
		{
			$aData['amTypeOptions'][] = $this->setupTranslateFields($type);
		}

        $aViewUrls['translateformheader_view'][] = $aData;
        $aViewUrls['output'] = '';
		// Define content of each tab
		foreach( $tab_names as $type )
		{
			$amTypeOptions = $this->setupTranslateFields($type);
			$type2 = $amTypeOptions["associated"];

			$associated = FALSE;
			if ( ! empty($type2) )
			{
				$associated = TRUE;
				$amTypeOptions2 = $this->setupTranslateFields($type2);
                $resultbase2 = $this->query($type, "querybase", $iSurveyID, $tolang, $baselang);
				$resultto2 = $this->query($type, "queryto", $iSurveyID, $tolang, $baselang);
			}
			// Setup form
			// start a counter in order to number the input fields for each record
			$i = 0;
			$evenRow = FALSE;
			$all_fields_empty = TRUE;

			$resultbase = $this->query($type, "querybase", $iSurveyID, $tolang, $baselang);
			$resultto = $this->query($type, "queryto", $iSurveyID, $tolang, $baselang);
			$aData['baselangdesc'] = $baselangdesc;
			$aData['tolangdesc'] = $tolangdesc;
			$aData['type'] = $type;
			$aData['translateTabs'] = $this->displayTranslateFieldsHeader($baselangdesc, $tolangdesc, $type);
			$aViewUrls['output'] .= $this->getController()->render("/admin/translate/translatetabs_view", $aData, true);
			foreach ( $resultbase as $rowfrom )
			{
				$textfrom = htmlspecialchars_decode($rowfrom[$amTypeOptions["dbColumn"]]);
				$textto = $resultto[$i][$amTypeOptions["dbColumn"]];
				if ( $associated )
				{
					$textfrom2 = htmlspecialchars_decode($resultbase2[$i][$amTypeOptions2["dbColumn"]]);
					$textto2 = $resultto2[$i][$amTypeOptions2["dbColumn"]];
				}

				$gid = ( $amTypeOptions["gid"] == TRUE ) ? $gid = $rowfrom['gid'] : NULL;
				$qid = ( $amTypeOptions["qid"] == TRUE ) ? $qid = $rowfrom['qid'] : NULL;

				$textform_length = strlen(trim($textfrom));
				if ( $textform_length > 0 )
				{
					$all_fields_empty = FALSE;
				}

				$aData['textfrom'] = $textfrom;
				$aData['textfrom2'] = $textfrom2;
				$aData['textto'] = $textto;
				$aData['textto2'] = $textto2;
				$aData['rowfrom'] = $rowfrom;
				$aData['rowfrom2'] = $resultbase2;
				$aData['evenRow'] = $evenRow;
				$aData['gid'] = $gid;
				$aData['qid'] = $qid;
				$aData['amTypeOptions'] = $amTypeOptions;
				$aData['amTypeOptions2'] = $amTypeOptions2;
				$aData['i'] = $i;
				$aData['type'] = $type;
				$aData['type2'] = $type2;
				$aData['associated'] = $associated;

				$evenRow = !($evenRow);
				$aData['translateFields'] = $this->displayTranslateFields($iSurveyID, $gid, $qid, $type,
											$amTypeOptions, $baselangdesc, $tolangdesc, $textfrom, $textto, $i, $rowfrom, $evenRow);
				if ($associated && strlen(trim((string)$textfrom2)) > 0)
				{
					$evenRow = !($evenRow);
					$aData['translateFields'] .= $this->displayTranslateFields($iSurveyID, $gid, $qid, $type2,
											$amTypeOptions2, $baselangdesc, $tolangdesc, $textfrom2, $textto2, $i, $resultbase2[$i], $evenRow);
				}

				$aViewUrls['output'] .= $this->getController()->render("/admin/translate/translatefields_view", $aData, true);

				$i++;
			} // end while

			$aData['all_fields_empty'] = $all_fields_empty;
			$aData['translateFieldsFooter'] = $this->displayTranslateFieldsFooter();
            $aData['bReadOnly']=!Permission::model()->hasSurveyPermission($iSurveyID, 'translations', 'update');
			$aViewUrls['output'] .= $this->getController()->render("/admin/translate/translatefieldsfooter_view", $aData, true);
		} // end foreach

		// Submit button
		$aViewUrls['translatefooter_view'][] = $aData;

        return $aViewUrls;
	}

    /**
    * showTranslateAdminmenu() creates the main menu options for the survey translation page
    * @param string $iSurveyID The survey ID
    * @param string $survey_title
    * @param string $tolang
    * @param string $activated
    * @param string $scriptname
    * @return string
    */
    private function showTranslateAdminmenu($iSurveyID, $survey_title, $tolang)
    {
        $clang = Yii::app()->lang;
        $publicurl = Yii::app()->getConfig('publicurl');
		$menuitem_url = "{$publicurl}/index.php?sid={$iSurveyID}&newtest=Y&lang=";

		$adminmenu = "";
        $adminmenu .= CHtml::openTag('div', array('class'=>'menubar'));
        $adminmenu .= CHtml::openTag('div', array('class'=>'menubar-title ui-widget-header'));
        $adminmenu .= CHtml::tag('strong', array(), $clang->gT("Translate survey") . ": $survey_title");
        $adminmenu .= CHtml::closeTag("div");
        $adminmenu .= CHtml::openTag('div', array('class'=>'menubar-main'));
        $adminmenu .= CHtml::openTag('div', array('class'=>'menubar-left'));

        // Return to survey administration button
        $adminmenu .= $this->menuItem(
							$clang->gT("Return to survey administration"),
							"Administration",
							"home.png",
							$this->getController()->createUrl("admin/survey/sa/view/surveyid/{$iSurveyID}/")
						);

        // Separator
        $adminmenu .= $this->menuSeparator();

        // Test / execute survey button
        if ( ! empty ($tolang) )
        {
			$adminmenu .= $this->_getSurveyButton($iSurveyID, $menuitem_url);
		}

        // End of survey-bar-left
		$adminmenu .= CHtml::closeTag('div');


        // Survey language list
		$adminmenu .= $this->_getLanguageList($iSurveyID, $tolang);
		$adminmenu .= CHtml::closeTag('div');
		$adminmenu .= CHtml::closeTag('div');

        return $adminmenu;
    }

	/*
	* _getSurveyButton() returns test / execute survey button
	* @param string $iSurveyID Survey id
	* @param string $menuitem_url Menu item url
	*/
	private function _getSurveyButton($iSurveyID, $menuitem_url)
	{
		$survey_button = "";

        $imageurl = Yii::app()->getConfig("adminimageurl");
        $clang = Yii::app()->lang;

        $baselang = Survey::model()->findByPk($iSurveyID)->language;
        $langs = Survey::model()->findByPk($iSurveyID)->additionalLanguages;

        $surveyinfo = Survey::model()->with(array('languagesettings'=>array('condition'=>'surveyls_language=language')))->findByPk($iSurveyID);
        $surveyinfo = array_merge($surveyinfo->attributes, $surveyinfo->languagesettings[0]->attributes);

		$surveyinfo = array_map('flattenText', $surveyinfo);
		$menutext = ( $surveyinfo['active'] == "N" ) ? $clang->gT("Test this survey") : $clang->gT("Execute this survey");

		if ( count($langs) == 0 )
		{
			$survey_button .= $this->menuItem(
								$menutext,
								'',
								"do.png",
								$menuitem_url . $baselang
							);
		}
		else
		{
			$icontext = $clang->gT($menutext);

			$img_tag = CHtml::image($imageurl . '/do.png', $icontext);
			$survey_button .= CHtml::link($img_tag, '#', array(
				'id' 		=> 	'dosurvey',
				'class' 	=> 	'dosurvey',
				'accesskey' => 	'd'
			));

			$tmp_survlangs = $langs;
			$tmp_survlangs[] = $baselang;
			rsort($tmp_survlangs);

			// Test Survey Language Selection Popup
			$survey_button .= CHtml::openTag(
									'div',
									array(
										'class' => 'langpopup',
										'id' => 'dosurveylangpopup'
									)
								);

			$survey_button .= $clang->gT("Please select a language:") . CHtml::openTag('ul');

			foreach ( $tmp_survlangs as $tmp_lang )
			{
				$survey_button .= CHtml::tag('li', array(),
					CHtml::link(getLanguageNameFromCode($tmp_lang, FALSE), $menuitem_url . $tmp_lang, array(
						'target' 	=> 	'_blank',
						'onclick' 	=> 	"$('.dosurvey').qtip('hide');",
						'accesskey' => 	'd'
					))
				);
			}
			$survey_button .= CHtml::closeTag('ul');
			$survey_button .= CHtml::closeTag('div');
		}

		return $survey_button;
	}

	/*
	* _getLanguageList() returns survey language list
	* @param string $iSurveyID Survey id
	* @param string @clang Language object
	* @param string $tolang The target translation code
	*/
	private function _getLanguageList($iSurveyID, $tolang)
	{
		$language_list = "";

        $clang = Yii::app()->lang;

        $langs = Survey::model()->findByPk($iSurveyID)->additionalLanguages;
        $supportedLanguages = getLanguageData(FALSE,Yii::app()->session['adminlang']);

		$language_list .= CHtml::openTag('div', array('class'=>'menubar-right')); // Opens .menubar-right div
		$language_list .= CHtml::tag('label', array('for'=>'translationlanguage'), $clang->gT("Translate to") . ":");
		$language_list .= CHtml::openTag(
							'select',
							array(
								'id' => 'translationlanguage',
								'name' => 'translationlanguage',
								'onchange' => "window.open(this.options[this.selectedIndex].value,'_top')"
							)
						);

        if ( count(Survey::model()->findByPk($iSurveyID)->additionalLanguages) > 1 )
        {
			$selected = ( ! isset($tolang) ) ? "selected" : "";

			$language_list .= CHtml::tag(
								'option',
								array(
									'selected' => $selected,
									'value' => $this->getController()->createUrl("admin/translate/sa/index/surveyid/{$iSurveyID}/")
								),
								$clang->gT("Please choose...")
							);
        }

        foreach( $langs as $lang )
        {
            $selected = ( $tolang == $lang ) ? "selected" : "";

            $tolangtext = $supportedLanguages[$lang]['description'];
			$language_list .= CHtml::tag(
								'option',
								array(
									'selected' => $selected,
									'value' => $this->getController()->createUrl("admin/translate/sa/index/surveyid/{$iSurveyID}/lang/{$lang}")
								),
								$tolangtext
							);
        }

		$language_list .= CHtml::closeTag('select');
		$language_list .= CHtml::closeTag('div'); // End of menubar-right

		return $language_list;
	}

    /**
    * setupTranslateFields() creates a customised array with database query
    * information for use by survey translation
    * @param string $iSurveyID Survey id
    * @param string $type Type of database field that is being translated, e.g. title, question, etc.
    * @param string $baselang The source translation language code, e.g. "En"
    * @param string $tolang The target translation language code, e.g. "De"
    * @param string $new The new value of the translated string
    * @param string $id1 An index variable used in the database select and update query
    * @param string $id2 An index variable used in the database select and update query
    * @return array
    */
    private function setupTranslateFields($type)
    {
        $clang = Yii::app()->lang;

		$aData = array();

        switch ( $type )
        {
            case 'title':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_title',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Survey title and description"),
					'HTMLeditorType' => "title",
					'HTMLeditorDisplay' => "Inline",
					'associated' => "description"
				);
			break;

            case 'description':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_description',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Description:"),
					'HTMLeditorType' => "description",
					'HTMLeditorDisplay' => "Inline",
					'associated' => ""
				);
			break;

            case 'welcome':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_welcometext',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Welcome and end text"),
					'HTMLeditorType' => "welcome",
					'HTMLeditorDisplay' => "Inline",
					'associated' => "end"
				);
			break;

            case 'end':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_endtext',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("End message:"),
					'HTMLeditorType' => "end",
					'HTMLeditorDisplay' => "Inline",
					'associated' => ""
				);
			break;

            case 'group':
				$aData = array(
					'type' => 2,
					'dbColumn' => 'group_name',
					'id1' => 'gid',
					'id2' => '',
					'gid' => TRUE,
					'qid' => FALSE,
					'description' => $clang->gT("Question groups"),
					'HTMLeditorType' => "group",
					'HTMLeditorDisplay' => "Popup",
					'associated' => "group_desc"
				);
			break;

            case 'group_desc':
				$aData = array(
					'type' => 2,
					'dbColumn' => 'description',
					'id1' => 'gid',
					'id2' => '',
					'gid' => TRUE,
					'qid' => FALSE,
					'description' => $clang->gT("Group description"),
					'HTMLeditorType' => "group_desc",
					'HTMLeditorDisplay' => "Popup",
					'associated' => ""
				);
			break;

            case 'question':
				$aData = array(
					'type' => 3,
					'dbColumn' => 'question',
					'id1' => 'qid',
					'id2' => '',
					'gid' => TRUE,
					'qid' => TRUE,
					'description' => $clang->gT("Questions"),
					'HTMLeditorType' => "question",
					'HTMLeditorDisplay' => "Popup",
					'associated' => "question_help"
				);
			break;

            case 'question_help':
				$aData = array(
					'type' => 3,
					'dbColumn' => 'help',
					'id1' => 'qid',
					'id2' => '',
					'gid' => TRUE,
					'qid' => TRUE,
					'description' => $clang->gT("Question help"),
					'HTMLeditorType' => "question_help",
					'HTMLeditorDisplay' => "Popup",
					'associated' => ""
				);
			break;

            case 'subquestion':
				$aData = array(
					'type' => 4,
					'dbColumn' => 'question',
					'id1' => 'qid',
					'id2' => '',
					'gid' => TRUE,
					'qid' => TRUE,
					'description' => $clang->gT("Subquestions"),
					'HTMLeditorType' => "question",
					'HTMLeditorDisplay' => "Popup",
					'associated' => ""
				);
			break;

            case 'answer': // TODO not touched
				$aData = array(
					'type' => 5,
					'dbColumn' => 'answer',
					'id1' => 'qid',
					'id2' => 'code',
					'gid' => FALSE,
					'qid' => TRUE,
					'description' => $clang->gT("Answer options"),
					'HTMLeditorType' => "subquestion",
					'HTMLeditorDisplay' => "Popup",
					'associated' => ""
				);
			break;

            case 'emailinvite':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_invite_subj',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Invitation email subject"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "Popup",
					'associated' => "emailinvitebody"
				);
			break;

            case 'emailinvitebody':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_invite',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Invitation email"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => ""
				);
			break;

            case 'emailreminder':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_remind_subj',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Reminder email subject"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => "emailreminderbody"
				);
			break;

            case 'emailreminderbody':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_remind',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Reminder email"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => ""
				);
			break;

            case 'emailconfirmation':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_confirm_subj',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Confirmation email subject"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => "emailconfirmationbody"
				);
			break;

            case 'emailconfirmationbody':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_confirm',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Confirmation email"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => ""
				);
			break;

            case 'emailregistration':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_register_subj',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Registration email subject"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => "emailregistrationbody"
				);
			break;

            case 'emailregistrationbody':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_register',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
                    'description' => $clang->gT("Registration email"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => ""
				);
			break;

            case 'email_confirm':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_confirm_subj',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
					'description' => $clang->gT("Confirmation email subject"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => "email_confirmbody"
				);
			break;

            case 'email_confirmbody':
				$aData = array(
					'type' => 1,
					'dbColumn' => 'surveyls_email_confirm',
					'id1' => '',
					'id2' => '',
					'gid' => FALSE,
					'qid' => FALSE,
                    'description' => $clang->gT("Confirmation email"),
					'HTMLeditorType' => "email",
					'HTMLeditorDisplay' => "",
					'associated' => ""
				);
			break;
        }
        return $aData;
    }

	private function query($type, $action, $iSurveyID, $tolang, $baselang, $id1 = "", $id2 = "", $new = "")
	{
		$amTypeOptions = array();
        switch ($action)
        {
            case "queryto":
                $baselang = $tolang;
            case "querybase":
                switch ( $type )
                {
                    case 'title':
                    case 'description':
                    case 'welcome':
                    case 'end':
                    case 'emailinvite':
                    case 'emailinvitebody':
                    case 'emailreminder':
                    case 'emailreminderbody':
                    case 'emailconfirmation':
                    case 'emailconfirmationbody':
                    case 'emailregistration':
                    case 'emailregistrationbody':
                    case 'email_confirm':
                    case 'email_confirmbody':
                        return SurveyLanguageSetting::model()->findAllByPk(array('surveyls_survey_id'=>$iSurveyID, 'surveyls_language'=>$baselang));
                    case 'group':
                    case 'group_desc':
                        return Groups::model()->findAllByAttributes(array('sid'=>$iSurveyID, 'language'=>$baselang), array('order' => 'gid'));
                    case 'question':
                    case 'question_help':
                        return Questions::model()->with('parents', 'groups')->findAllByAttributes(array('sid' => $iSurveyID,'language' => $baselang,'parent_qid' => 0), array('order' => 'groups.group_order, t.question_order, t.scale_id'));
                    case 'subquestion':
                        return Questions::model()->with('parents', 'groups')->findAllByAttributes(array('sid' => $iSurveyID,'language' => $baselang), array('order' => 'groups.group_order, parents.question_order, t.scale_id, t.question_order', 'condition'=>'parents.language=:baselang1 AND groups.language=:baselang2 AND t.parent_qid>0', 'params'=>array(':baselang1'=>$baselang,':baselang2'=>$baselang)));
                    case 'answer':
                        return Answers::model()->with('questions', 'groups')->findAllByAttributes(array('language' => $baselang), array('order' => 'groups.group_order, questions.question_order, t.scale_id, t.sortorder', 'condition'=>'questions.sid=:sid AND questions.language=:baselang1 AND groups.language=:baselang2', 'params'=>array(':baselang1'=>$baselang, ':baselang2'=>$baselang, ':sid' => $iSurveyID)));
                }
            case "queryupdate":
                switch ( $type )
                {
                    case 'title':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_title'=>$new));
                    case 'description':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_description'=>$new));
                    case 'welcome':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_welcometext'=>$new));
                    case 'end':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_endtext'=>$new));
                    case 'emailinvite':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_invite_subj'=>$new));
                    case 'emailinvitebody':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_invite'=>$new));
                    case 'emailreminder':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_remind_subj'=>$new));
                    case 'emailreminderbody':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_remind'=>$new));
                    case 'emailconfirmation':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_confirm_subj'=>$new));
                    case 'emailconfirmationbody':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_confirm'=>$new));
                    case 'emailregistration':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_register_subj'=>$new));
                    case 'emailregistrationbody':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_register'=>$new));
                    case 'email_confirm':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_confirm_subject'=>$new));
                    case 'email_confirmbody':
                        return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id'=>$iSurveyID,'surveyls_language'=>$tolang),array('surveyls_email_confirm'=>$new));
                    case 'group':
                        return Groups::model()->updateByPk(array('gid'=>$id1, 'language'=>$tolang),array('group_name' => $new), 'sid=:sid', array(':sid'=>$iSurveyID));
                    case 'group_desc':
                        return Groups::model()->updateByPk(array('gid'=>$id1, 'language'=>$tolang),array('description' => $new), 'sid=:sid', array(':sid'=>$iSurveyID));
                    case 'question':
                        return Questions::model()->updateByPk(array('qid'=>$id1, 'language'=>$tolang),array('question' => $new), 'sid=:sid AND parent_qid=0', array(':sid'=>$iSurveyID));
                    case 'question_help':
                        return Questions::model()->updateByPk(array('qid'=>$id1, 'language'=>$tolang),array('help' => $new), 'sid=:sid AND parent_qid=0', array(':sid'=>$iSurveyID));
                    case 'subquestion':
                        return Questions::model()->updateByPk(array('qid'=>$id1, 'language'=>$tolang),array('question' => $new), 'sid=:sid', array(':sid'=>$iSurveyID));
                    case 'answer':
                        return Answers::model()->updateByPk(array('qid'=>$id1, 'code'=>$id2, 'language'=>$tolang, 'scale_id'=>0),array('answer' => $new));
                        // @todo: FIXME for dual scale answer options
                }

        }
	}

    /**
    * displayTranslateFieldsHeader() Formats and displays header of translation fields table
    * @param string $baselangdesc The source translation language, e.g. "English"
    * @param string $tolangdesc The target translation language, e.g. "German"
    * @return string $translateoutput
    */
    private function displayTranslateFieldsHeader($baselangdesc, $tolangdesc, $type)
    {
        $clang = Yii::app()->lang;
		$translateoutput = "";
        $translateoutput .= CHtml::openTag('table', array('class'=>'translate'));
        $translateoutput .= CHtml::openTag('tr');
        if ($type=='question' || $type=='subquestion' || $type=='question_help' || $type=='answer')
        {
            $translateoutput.='<colgroup valign="top" width="8%" />';
        }
        $translateoutput .= '<colgroup valign="top" width="37" />';
		$translateoutput .= '<colgroup valign="top" width="55%" />';
        if ($type=='question' || $type=='subquestion' || $type=='question_help' || $type=='answer')
        {
            $translateoutput .= CHtml::tag('th', array(), CHtml::tag('b', array(), $clang->gT('Question code / ID')));
        }
        $translateoutput .= CHtml::tag('th', array(), CHtml::tag('b', array(), $baselangdesc));
        $translateoutput .= CHtml::tag('th', array(), CHtml::tag('b', array(), $tolangdesc));
        $translateoutput .= CHtml::closeTag("tr");

        return $translateoutput;
    }

    /**
    * displayTranslateFields() Formats and displays translation fields (base language as well as to language)
    * @param string $iSurveyID Survey id
    * @param string $gid Group id
    * @param string $qid Question id
    * @param string $type Type of database field that is being translated, e.g. title, question, etc.
    * @param array $amTypeOptions Array containing options associated with each $type
    * @param string $baselangdesc The source translation language, e.g. "English"
    * @param string $tolangdesc The target translation language, e.g. "German"
    * @param string $textfrom The text to be translated in source language
    * @param string $textto The text to be translated in target language
    * @param integer $i Counter
    * @param string $rowfrom Contains current row of database query
    * @param boolean $evenRow TRUE for even rows, FALSE for odd rows
    * @return string $translateoutput
    */
    private function displayTranslateFields($iSurveyID, $gid, $qid, $type, $amTypeOptions,
    $baselangdesc, $tolangdesc, $textfrom, $textto, $i, $rowfrom, $evenRow)
    {
        $translateoutput = "";
		$translateoutput .= CHtml::openTag('tr', array('class' => ( $evenRow ) ? 'odd' : 'even'));

        $value1 = ( ! empty($amTypeOptions["id1"]) ) ? $rowfrom[$amTypeOptions["id1"]] : "";
        $value2 = ( ! empty($amTypeOptions["id2"]) ) ? $rowfrom[$amTypeOptions["id2"]] : "";

        // Display text in original language
        // Display text in foreign language. Save a copy in type_oldvalue_i to identify changes before db update
        if ($type=='answer')
        {
            //print_r($rowfrom->attributes);die();
            $translateoutput .= "<td>".htmlspecialchars($rowfrom->questions->title)." ({$rowfrom->questions->qid})</td>\n";
        }
        if ($type=='question_help' || $type=='question')
        {
            //print_r($rowfrom->attributes);die();
            $translateoutput .= "<td>".htmlspecialchars($rowfrom->title)." ({$rowfrom->qid})</td>\n";
        }
        else if ($type=='subquestion')
        {
            //print_r($rowfrom->attributes);die();
            $translateoutput .= "<td>".htmlspecialchars($rowfrom->parents->title)." ({$rowfrom->parents->qid})</td>\n";
        }

		$translateoutput .= CHtml::tag(
								'td',
								array(
									'class' => '_from_',
									'id' => "${type}_from_${i}"
								),
								showJavaScript($textfrom)
							);
        $translateoutput .= CHtml::openTag('td');
		$translateoutput .= CHtml::hiddenField("{$type}_id1_{$i}", $value1);
		$translateoutput .= CHtml::hiddenField("{$type}_id2_{$i}", $value2);

        $nrows = max($this->calc_nrows($textfrom), $this->calc_nrows($textto));

		$translateoutput .= CHtml::hiddenField("{$type}_oldvalue_{$i}", $textto);
		$translateoutput .= CHtml::textArea("{$type}_newvalue_{$i}", $textto,
								array(
									'cols' => '75',
									'rows' => $nrows,
								)
							);

		$htmleditor_data = array(
			"edit" . $type ,
			$type . "_newvalue_" . $i,
			htmlspecialchars($textto),
			$iSurveyID,
			$gid,
			$qid,
			"translate" . $amTypeOptions["HTMLeditorType"]
		);
		$translateoutput .= $this->_loadEditor($amTypeOptions, $htmleditor_data);

        $translateoutput .= CHtml::closeTag("td");
        $translateoutput .= CHtml::closeTag("tr");

        return $translateoutput;
    }

	private function _loadEditor($htmleditor, $aData)
	{
		$editor_function = "";

        if ( $htmleditor["HTMLeditorDisplay"] == "Inline" OR  $htmleditor["HTMLeditorDisplay"] == "" )
        {
            $editor_function = "getEditor";
        }
		else if ( $htmleditor["HTMLeditorDisplay"] == "Popup" )
        {
            $editor_function = "getPopupEditor";
			$aData[2] = urlencode($htmleditor['description']);
        }

		return call_user_func_array($editor_function, $aData);
	}

    /**
    * calc_nrows($subject) calculates the vertical size of textbox for survey translation.
    * The function adds the number of line breaks <br /> to the number of times a string wrap occurs.
    * @param string $subject The text string that is being translated
    * @return integer
    */
    private function calc_nrows( $subject )
    {
        // Determines the size of the text box
        // A proxy for box sixe is string length divided by 80
        $pattern = "(<br..?>)";
        $pattern = '[(<br..?>)|(/\n/)]';

        $nrows_newline = preg_match_all($pattern, $subject, $matches);

		$subject_length = strlen((string)$subject);
        $nrows_char = ceil($subject_length / 80);

        return $nrows_newline + $nrows_char;
    }

    /**
    * displayTranslateFieldsFooter() Formats and displays footer of translation fields table
    * @return string $translateoutput
    */
    private function displayTranslateFieldsFooter()
    {
		$translateoutput = CHtml::closeTag("table");

        return $translateoutput;
    }

    /**
    * menuItem() creates a menu item with text and image in the admin screen menus
    * @param string $jsMenuText
    * @return string
    */
    private function menuItem( $jsMenuText, $menuImageText, $menuImageFile, $scriptname)
    {

        $imageurl = Yii::app()->getConfig("adminimageurl");

		$img_tag = CHtml::image($imageurl . "/" . $menuImageFile, $jsMenuText, array('name'=>$menuImageText));
		$menuitem = CHtml::link($img_tag, '#', array(
			'onclick' => "window.open('{$scriptname}', '_top')"
		));
        return $menuitem;
    }

    /**
    * menuSeparator() creates a separator bar in the admin screen menus
    * @return string
    */
    private function menuSeparator()
    {

        $imageurl = Yii::app()->getConfig("adminimageurl");

		$image = CHtml::image($imageurl . "/separator.gif", '');
        return $image;
    }

    /*
    * translate_google_api.php
    * Creates a JSON interface for the auto-translate feature
    */
    private function translate_google_api()
    {
        header('Content-type: application/json');

        $sBaselang   = Yii::app()->getRequest()->getPost('baselang');
        $sTolang     = Yii::app()->getRequest()->getPost('tolang');
        $sToconvert  = Yii::app()->getRequest()->getPost('text');

        $aSearch     = array('zh-Hans','zh-Hant-HK','zh-Hant-TW',
						'nl-informal','de-informal','it-formal','pt-BR','es-MX','nb','nn');
        $aReplace    = array('zh-CN','zh-TW','zh-TW','nl','de','it','pt','es','no','no');

        $sTolang = str_replace($aSearch, $aReplace, $sTolang);

		$error = FALSE;
        try
		{
            Yii::app()->loadLibrary('admin/gtranslate/GTranslate');
			$gtranslate = new Gtranslate();
            $objGt = $gtranslate;

            // Gtranslate requires you to run function named XXLANG_to_XXLANG
            $sProcedure = $sBaselang . "_to_" . $sTolang;

            $parts = LimeExpressionManager::SplitStringOnExpressions($sToconvert);

            $sparts = array();
            foreach($parts as $part)
            {
                if ($part[2]=='EXPRESSION')
                {
                    $sparts[] = $part[0];
                }
                else
                {
                    $convertedPart = $objGt->$sProcedure($part[0]);
                    $convertedPart  = str_replace("<br>","\r\n",$convertedPart);
                    $convertedPart  = html_entity_decode(stripcslashes($convertedPart));
                    $sparts[] = $convertedPart;
                }
            }
            $sOutput = implode(' ', $sparts);
        }
		catch ( GTranslateException $ge )
		{
            // Get the error message and build the ouput array
			$error = TRUE;
            $sOutput  = $ge->getMessage();
        }

		$aOutput = array(
			'error'     =>  $error,
			'baselang'  =>  $sBaselang,
			'tolang'    =>  $sTolang,
			'converted' =>  $sOutput
		);

        return ls_json_encode($aOutput) . "\n";
    }

    /**
     * Renders template(s) wrapped in header and footer
     *
     * @param string $sAction Current action, the folder to fetch views from
     * @param string|array $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     */
    protected function _renderWrappedTemplate($sAction = 'translate', $aViewUrls = array(), $aData = array())
    {
        $aData['display']['menu_bars'] = false;
        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData);
    }
}
