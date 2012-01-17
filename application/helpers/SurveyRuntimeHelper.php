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
 *	$Id$
 */

class SurveyRuntimeHelper {

    /**
    * Main function
    *
    * @param mixed $surveyid
    * @param mixed $args
    */
 	function run($surveyid,$args) {
        extract($args);

        // $LEMdebugLevel - customizable debugging for Lime Expression Manager
        $LEMdebugLevel = 0;   // LEM_DEBUG_TIMING;    // (LEM_DEBUG_TIMING + LEM_DEBUG_VALIDATION_SUMMARY + LEM_DEBUG_VALIDATION_DETAIL);
        switch ($thissurvey['format'])
        {
            case "A": //All in one
                $surveyMode = 'survey';
                break;
            default:
            case "S": //One at a time
                $surveyMode = 'question';
                break;
            case "G": //Group at a time
                $surveyMode = 'group';
                break;
        }
        $surveyOptions = array(
            'active' => ($thissurvey['active'] == 'Y'),
            'allowsave' => ($thissurvey['allowsave'] == 'Y'),
            'anonymized' => ($thissurvey['anonymized'] != 'N'),
            'assessments' => ($thissurvey['assessments'] == 'Y'),
            'datestamp' => ($thissurvey['datestamp'] == 'Y'),
            'hyperlinkSyntaxHighlighting' => (($LEMdebugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY), // TODO set this to true if in admin mode but not if running a survey
            'ipaddr' => ($thissurvey['ipaddr'] == 'Y'),
            'refurl' => (($thissurvey['refurl'] == "Y") ? $_SESSION[$surveyid]['refurl'] : NULL),
            'savetimings' => ($thissurvey['savetimings'] == "Y"),
            'surveyls_dateformat' => (isset($thissurvey['surveyls_dateformat']) ? $thissurvey['surveyls_dateformat'] : 1),
            'startlanguage' => (isset($_SESSION[$surveyid]['s_lang']) ? $_SESSION[$surveyid]['s_lang'] : 'en'),
            'target' => (isset($uploaddir) ? "{$uploaddir}/surveys/{$thissurvey['sid']}/files/" : "/temp/{$thissurvey['sid']}/files"),
            'tempdir' => (isset($tempdir) ? $tempdir : '/temp/'),
            'timeadjust' => (isset($timeadjust) ? $timeadjust : 0),
            'token' => (isset($clienttoken) ? $clienttoken : NULL),
        );

        //Security Checked: POST, GET, SESSION, REQUEST, returnglobal, DB
        $previewgrp = false;
        if ($surveyMode == 'group' && isset($param['action']) && ($param['action'] == 'previewgroup'))
        {
            $previewgrp = true;
        }
//        if (isset($param['newtest']) && $param['newtest'] == "Y")
//            setcookie("limesurvey_timers", "0");   //@todo fix - sometimes results in headers already sent error
        $show_empty_group = false;

        if ($previewgrp)
        {
            $_SESSION[$surveyid]['prevstep'] = 1;
            $_SESSION[$surveyid]['maxstep'] = 0;
        }
        else
        {
            //RUN THIS IF THIS IS THE FIRST TIME , OR THE FIRST PAGE ########################################
            if (!isset($_SESSION[$surveyid]['step']))  //  || !$_SESSION['step']) - don't do this for step0, else rebuild the session
            {
                $totalquestions = buildsurveysession($surveyid);
                LimeExpressionManager::StartSurvey($thissurvey['sid'], $surveyMode, $surveyOptions, false, $LEMdebugLevel);
                $_SESSION[$surveyid]['step'] = 0;
                if ($surveyMode == 'survey')
                {
                    $move = "movenext"; // to force a call to NavigateForwards()
                }
                else if (isset($thissurvey['showwelcome']) && $thissurvey['showwelcome'] == 'N')
                {
                    //If explicitply set, hide the welcome screen
                    $_SESSION[$surveyid]['step'] = 0;
                    $move = "movenext";
                }
            }

            if (!isset($_SESSION[$surveyid]['totalsteps']))
            {
                $_SESSION[$surveyid]['totalsteps'] = 0;
            }
            if (!isset($_SESSION[$surveyid]['maxstep']))
            {
                $_SESSION[$surveyid]['maxstep'] = 0;
            }
            $_SESSION[$surveyid]['prevstep'] = $_SESSION[$surveyid]['step'];

            if (isset($_SESSION[$surveyid]['LEMpostKey']) && (!isset($_POST['LEMpostKey']) || ($_POST['LEMpostKey'] != $_SESSION[$surveyid]['LEMpostKey'])))
            {
                // then trying to resubmit (e.g. Next, Previous, Submit) from a cached copy of the page
                // Simply re-display the current page without re-processing POST or re-validating input.  Means user will lose whatever data entry the just tried
                // Also flash a message
                $moveResult = LimeExpressionManager::GetLastMoveResult();
                $move = "movenext"; // so will re-display the survey
                $invalidLastPage = true;
                $vpopup = "<script type=\"text/javascript\">\n
                <!--\n $(document).ready(function(){
                    alert(\"" . $clang->gT("Please use the LimeSurvey navigation buttons or index.  It appears you attempted to use the browser back button to re-submit a page.", "js") . "\");});\n //-->\n
                </script>\n";
            }
            else
            {
                //Move current step ###########################################################################
                if (isset($move) && $move == 'moveprev' && ($thissurvey['allowprev'] == 'Y' || $thissurvey['allowjumps'] == 'Y'))
                {
                    $moveResult = LimeExpressionManager::NavigateBackwards();
                    if ($moveResult['at_start'])
                    {
                        $_SESSION[$surveyid]['step'] = 0;
                        unset($moveResult); // so display welcome page again
                    }
                }
                if (isset($move) && $move == "movenext")
                {
                    if (isset($_SESSION[$surveyid]['LEMreload']))
                    {
                        LimeExpressionManager::StartSurvey($thissurvey['sid'], $surveyMode, $surveyOptions, false, $LEMdebugLevel);
                        $moveResult = LimeExpressionManager::JumpTo($_SESSION[$surveyid]['step'], false, false);   // if late in the survey, will re-validate contents, which may be overkill
                        unset($_SESSION[$surveyid]['LEMreload']);
                    }
                    else
                    {
                        $moveResult = LimeExpressionManager::NavigateForwards();
                    }
                }
                if (isset($move) && ($move == 'movesubmit'))
                {
                    if ($surveyMode == 'survey')
                    {
                        $moveResult = LimeExpressionManager::NavigateForwards();
                    }
                    else
                    {
                        // may be submitting from the navigation bar, in which case need to process all intervening questions
                        // in order to update equations and ensure there are no intervening relevant mandatory or relevant invalid questions
                        $moveResult = LimeExpressionManager::JumpTo($_SESSION[$surveyid]['totalsteps'] + 1, false);
                    }
                }
                if (isset($move) && (preg_match('/^changelang_/', $move)))
                {
                    // jump to current step using new language, processing POST values
                    $moveResult = LimeExpressionManager::JumpTo($_SESSION[$surveyid]['step'], false, true, false, true);  // do process the POST data
                }
                if (isset($move) && bIsNumericInt($move) && $thissurvey['allowjumps'] == 'Y')
                {
                    $move = (int) $move;
                    if ($move > 0 && (($move <= $_SESSION[$surveyid]['step']) || (isset($_SESSION[$surveyid]['maxstep']) && $move <= $_SESSION[$surveyid]['maxstep'])))
                    {
                        $moveResult = LimeExpressionManager::JumpTo($move, false);
                    }
                }
                if (!isset($moveResult) && !($surveyMode != 'survey' && $_SESSION[$surveyid]['step'] == 0))
                {
                    // Just in case not set via any other means, but don't do this if it is the welcome page
                    $moveResult = LimeExpressionManager::GetLastMoveResult();
                }
            }

            if (isset($moveResult))
            {
                if ($moveResult['finished'] == true)
                {
                    $move = 'movesubmit';
                }
                else
                {
                    $_SESSION[$surveyid]['step'] = $moveResult['seq'] + 1;  // step is index base 1
                    $stepInfo = LimeExpressionManager::GetStepIndexInfo($moveResult['seq']);
                }
                if ($move == "movesubmit" && $moveResult['finished'] == false)
                {
                    // then there are errors, so don't finalize the survey
                    $move = "movenext"; // so will re-display the survey
                    $invalidLastPage = true;
                }
            }

            // We do not keep the participant session anymore when the same browser is used to answer a second time a survey (let's think of a library PC for instance).
            // Previously we used to keep the session and redirect the user to the
            // submit page.

            if ($surveyMode != 'survey' && $_SESSION[$surveyid]['step'] == 0)
            {
                display_first_page();
                exit;
            }

            //CHECK IF ALL MANDATORY QUESTIONS HAVE BEEN ANSWERED ############################################
            //First, see if we are moving backwards or doing a Save so far, and its OK not to check:
            if (
                    (isset($move) && ($move == "moveprev" || (is_int($move) && $_SESSION[$surveyid]['prevstep'] == $_SESSION[$surveyid]['maxstep']) || $_SESSION[$surveyid]['prevstep'] == $_SESSION[$surveyid]['step'])) ||
                    (isset($_POST['saveall']) && $_POST['saveall'] == $clang->gT("Save your responses so far")))
            {
                if (Yii::app()->getConfig('allowmandbackwards') == 1)
                {
                    $backok = "Y";
                }
                else
                {
                    $backok = "N";
                }
            }
            else
            {
                $backok = "N";    // NA, since not moving backwards
            }
// TODO FIXME
            if ($thissurvey['active'] == "Y" && isset($_POST['saveall']))
            {
                // must do this here to process the POSTed values
                $moveResult = LimeExpressionManager::JumpTo($_SESSION[$surveyid]['step'], false);   // by jumping to current step, saves data so far

                require_once("save.php");   // for supporting functions only
                showsaveform(); // generates a form and exits, awaiting input
            }

            if ($thissurvey['active'] == "Y" && isset($_POST['saveprompt']))
            {
                // The response from the save form
                require_once("save.php");   // for supporting functions only
                // CREATE SAVED CONTROL RECORD USING SAVE FORM INFORMATION
                $flashmessage = savedcontrol();

                if (isset($errormsg) && $errormsg != "")
                {
                    showsaveform(); // reshow the form if there is an error
                }

                $moveResult = LimeExpressionManager::GetLastMoveResult();

                // TODO - does this work automatically for token answer persistence? Used to be savedsilent()
            }

            //Now, we check mandatory questions if necessary
            //CHECK IF ALL CONDITIONAL MANDATORY QUESTIONS THAT APPLY HAVE BEEN ANSWERED
            if (isset($moveResult) && !$moveResult['finished'])
            {
                $unansweredSQList = $moveResult['unansweredSQs'];
                if (strlen($unansweredSQList) > 0 && $backok != "N")
                {
                    $notanswered = explode('|', $unansweredSQList);
                }
                else
                {
                    $notanswered = array();
                }

                //CHECK INPUT
                $invalidSQList = $moveResult['invalidSQs'];
                if (strlen($invalidSQList) > 0 && $backok != "N")
                {
                    $notvalidated = explode('|', $invalidSQList);
                }
                else
                {
                    $notvalidated = array();
                }
            }

            // CHECK UPLOADED FILES
            // TMSW - Move this into LEM::NavigateForwards?
            $filenotvalidated = checkUploadedFileValidity($surveyid, $move, $backok);

            //SEE IF THIS GROUP SHOULD DISPLAY
            $show_empty_group = false;

            if ($_SESSION[$surveyid]['step'] == 0)
                $show_empty_group = true;

            $redata = compact(array_keys(get_defined_vars()));

            //SUBMIT ###############################################################################
            if ((isset($move) && $move == "movesubmit"))
            {
//                setcookie("limesurvey_timers", "", time() - 3600); // remove the timers cookies   //@todo fix - sometimes results in headers already sent error
                if ($thissurvey['refurl'] == "Y")
                {
                    if (!in_array("refurl", $_SESSION[$surveyid]['insertarray'])) //Only add this if it doesn't already exist
                    {
                        $_SESSION[$surveyid]['insertarray'][] = "refurl";
                    }
                }

                //COMMIT CHANGES TO DATABASE
                if ($thissurvey['active'] != "Y") //If survey is not active, don't really commit
                {
                    if ($thissurvey['assessments'] == "Y")
                    {
                        $assessments = doAssessment($surveyid);
                    }
                    if ($thissurvey['printanswers'] != 'Y')
                    {
                        killSession();
                    }

                    sendcacheheaders();
                    doHeader();

                    echo templatereplace(file_get_contents("$thistpl/startpage.pstpl"), array(), $redata);

                    //Check for assessments
                    if ($thissurvey['assessments'] == "Y" && $assessments)
                    {
                        echo templatereplace(file_get_contents("$thistpl/assessment.pstpl"), array(), $redata);
                    }

                    // fetch all filenames from $_SESSIONS['files'] and delete them all
                    // from the /upload/tmp/ directory
                    /* echo "<pre>";print_r($_SESSION);echo "</pre>";
                      for($i = 1; isset($_SESSION['files'][$i]); $i++)
                      {
                      unlink('upload/tmp/'.$_SESSION['files'][$i]['filename']);
                      }
                     */
                    $completed = $thissurvey['surveyls_endtext'];
                    $completed .= "<br /><strong><font size='2' color='red'>" . $clang->gT("Did Not Save") . "</font></strong><br /><br />\n\n";
                    $completed .= $clang->gT("Your survey responses have not been recorded. This survey is not yet active.") . "<br /><br />\n";
                    if ($thissurvey['printanswers'] == 'Y')
                    {
                        // ClearAll link is only relevant for survey with printanswers enabled
                        // in other cases the session is cleared at submit time
                        $completed .= "<a href='" . Yii::app()->getController()->createUrl("survey/index/sid/{$surveyid}/move/clearall") . "'>" . $clang->gT("Clear Responses") . "</a><br /><br />\n";
                    }
                }
                else //THE FOLLOWING DEALS WITH SUBMITTING ANSWERS AND COMPLETING AN ACTIVE SURVEY
                {
                    if ($thissurvey['usecookie'] == "Y" && $tokensexist != 1) //don't use cookies if tokens are being used
                    {
                        $cookiename = "PHPSID" . returnglobal('sid') . "STATUS";
//                        setcookie("$cookiename", "COMPLETE", time() + 31536000); //Cookie will expire in 365 days   //@todo fix - sometimes results in headers already sent error
                    }

                    //Before doing the "templatereplace()" function, check the $thissurvey['url']
                    //field for limereplace stuff, and do transformations!
                    $thissurvey['surveyls_url'] = passthruReplace($thissurvey['surveyls_url'], $thissurvey);

                    $content = '';
                    $content .= templatereplace(file_get_contents("$thistpl/startpage.pstpl"), array(), $redata);

                    //Check for assessments
                    if ($thissurvey['assessments'] == "Y")
                    {
                        $assessments = doAssessment($surveyid);
                        if ($assessments)
                        {
                            $content .= templatereplace(file_get_contents("$thistpl/assessment.pstpl"), array(), $redata);
                        }
                    }

                    //Update the token if needed and send a confirmation email
                    if (isset($clienttoken) && $clienttoken)
                    {
                        submittokens();
                    }

                    //Send notifications

                    SendSubmitNotifications();


                    $content = '';

                    $content .= templatereplace(file_get_contents("$thistpl/startpage.pstpl"), array(), $redata);

                    //echo $thissurvey['url'];
                    //Check for assessments
                    if ($thissurvey['assessments'] == "Y")
                    {
                        $assessments = doAssessment($surveyid);
                        if ($assessments)
                        {
                            $content .= templatereplace(file_get_contents("$thistpl/assessment.pstpl"), array(), $redata);
                        }
                    }


                    if (trim(strip_tags($thissurvey['surveyls_endtext'])) == '')
                    {
                        $completed = "<br /><span class='success'>" . $clang->gT("Thank you!") . "</span><br /><br />\n\n"
                                . $clang->gT("Your survey responses have been recorded.") . "<br /><br />\n";
                    }
                    else
                    {
                        $completed = $thissurvey['surveyls_endtext'];
                    }

                    // Link to Print Answer Preview  **********
                    if ($thissurvey['printanswers'] == 'Y')
                    {
                        $completed .= "<br /><br />"
                                . "<a class='printlink' href='printanswers.php?sid=$surveyid'  target='_blank'>"
                                . $clang->gT("Print your answers.")
                                . "</a><br />\n";
                    }
                    //*****************************************

                    if ($thissurvey['publicstatistics'] == 'Y' && $thissurvey['printanswers'] == 'Y')
                    {
                        $completed .='<br />' . $clang->gT("or");
                    }

                    // Link to Public statistics  **********
                    if ($thissurvey['publicstatistics'] == 'Y')
                    {
                        $completed .= "<br /><br />"
                                . "<a class='publicstatisticslink' href='statistics_user.php?sid=$surveyid' target='_blank'>"
                                . $clang->gT("View the statistics for this survey.")
                                . "</a><br />\n";
                    }
                    //*****************************************

                    $_SESSION[$surveyid]['finished'] = true;
                    $_SESSION[$surveyid]['sid'] = $surveyid;

                    sendcacheheaders();
                    if (isset($thissurvey['autoredirect']) && $thissurvey['autoredirect'] == "Y" && $thissurvey['surveyls_url'])
                    {
                        //Automatically redirect the page to the "url" setting for the survey

                        $url = templatereplace($thissurvey['surveyls_url']);    // TODO - check safety of this - provides access to any replacement value
                        $url = passthruReplace($url, $thissurvey);
                        $url = str_replace("{SAVEDID}", $saved_id, $url);               // to activate the SAVEDID in the END URL
                        $url = str_replace("{TOKEN}", $clienttoken, $url);          // to activate the TOKEN in the END URL
                        $url = str_replace("{SID}", $surveyid, $url);              // to activate the SID in the END URL
                        $url = str_replace("{LANG}", $clang->getlangcode(), $url); // to activate the LANG in the END URL
                        header("Location: {$url}");
                    }


                    //if($thissurvey['printanswers'] != 'Y' && $thissurvey['usecookie'] != 'Y' && $tokensexist !=1)
                    if ($thissurvey['printanswers'] != 'Y')
                    {
                        killSession();
                    }

                    doHeader();
                    echo $content;
                }

                echo templatereplace(file_get_contents("$thistpl/completed.pstpl"), array(), $redata);
                echo "\n<br />\n";
                if ((($LEMdebugLevel & LEM_DEBUG_TIMING) == LEM_DEBUG_TIMING))
                {
                    echo LimeExpressionManager::GetDebugTimingMessage();
                }
                if ((($LEMdebugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY))
                {
                    echo "<table><tr><td align='left'><b>Group/Question Validation Results:</b>" . $moveResult['message'] . "</td></tr></table>\n";
                }
                echo templatereplace(file_get_contents("$thistpl/endpage.pstpl"));
                doFooter();
                exit;
            }
        }

        $redata = compact(array_keys(get_defined_vars()));

        // IF GOT THIS FAR, THEN DISPLAY THE ACTIVE GROUP OF QUESTIONSs
        //SEE IF $surveyid EXISTS ####################################################################
        if ($surveyExists < 1)
        {
            //SURVEY DOES NOT EXIST. POLITELY EXIT.
            echo templatereplace(file_get_contents("$thistpl/startpage.pstpl"), array(), $redata);
            echo "\t<center><br />\n";
            echo "\t" . $clang->gT("Sorry. There is no matching survey.") . "<br /></center>&nbsp;\n";
            echo templatereplace(file_get_contents("$thistpl/endpage.pstpl"), array(), $redata);
            doFooter();
            exit;
        }
        createFieldMap($surveyid);
        //GET GROUP DETAILS

        if ($surveyMode == 'group' && $previewgrp)
        {
//            setcookie("limesurvey_timers", "0"); //@todo fix - sometimes results in headers already sent error
            $_gid = sanitize_int($param['gid']);

            LimeExpressionManager::StartSurvey($thissurvey['sid'], 'group', $surveyOptions, false, $LEMdebugLevel);
            $gseq = LimeExpressionManager::GetGroupSeq($_gid);
            if ($gseq == -1)
            {
                echo $clang->gT('Invalid group number for this survey: ') . $_gid;
                exit;
            }
            $moveResult = LimeExpressionManager::JumpTo($gseq + 1, true);
            if (is_null($moveResult))
            {
                echo $clang->gT('This group contains no questions.  You must add questions to this group before you can preview it');
                exit;
            }
            if (isset($moveResult))
            {
                $_SESSION[$surveyid]['step'] = $moveResult['seq'] + 1;  // step is index base 1?
            }

            $stepInfo = LimeExpressionManager::GetStepIndexInfo($moveResult['seq']);
            $gid = $stepInfo['gid'];
            $groupname = $stepInfo['gname'];
            $groupdescription = $stepInfo['gtext'];
        }
        else
        {
            if (($show_empty_group) || !isset($_SESSION[$surveyid]['grouplist']))
            {
                $gid = -1; // Make sure the gid is unused. This will assure that the foreach (fieldarray as ia) has no effect.
                $groupname = $clang->gT("Submit your answers");
                $groupdescription = $clang->gT("There are no more questions. Please press the <Submit> button to finish this survey.");
            }
            else if ($surveyMode != 'survey')
            {

                $stepInfo = LimeExpressionManager::GetStepIndexInfo($moveResult['seq']);
                $gid = $stepInfo['gid'];
                $groupname = $stepInfo['gname'];
                $groupdescription = $stepInfo['gtext'];
            }
        }

        if ($_SESSION[$surveyid]['step'] > $_SESSION[$surveyid]['maxstep'])
        {
            $_SESSION[$surveyid]['maxstep'] = $_SESSION[$surveyid]['step'];
        }

        // If the survey uses answer persistence and a srid is registered in SESSION
        // then loadanswers from this srid
        /* Only survey mode used this - should all?
          if ($thissurvey['tokenanswerspersistence'] == 'Y' &&
          $thissurvey['anonymized'] == "N" &&
          isset($_SESSION['srid']) &&
          $thissurvey['active'] == "Y")
          {
          loadanswers();
          }
         */

        //******************************************************************************************************
        //PRESENT SURVEY
        //******************************************************************************************************

        $okToShowErrors = (!$previewgrp && (isset($invalidLastPage) || $_SESSION[$surveyid]['prevstep'] == $_SESSION[$surveyid]['step']));

        Yii::app()->getController()->loadHelper('qanda');
        setNoAnswerMode($thissurvey);

        //Iterate through the questions about to be displayed:
        $inputnames = array();

        foreach ($_SESSION[$surveyid]['grouplist'] as $gl)
        {
            $gid = $gl[0];
            $qnumber = 0;

            if ($surveyMode != 'survey')
            {
                $onlyThisGID = $stepInfo['gid'];
                if ($onlyThisGID != $gid)
                {
                    continue;
                }
            }

            // TMSW - could iterate through LEM::currentQset instead
            foreach ($_SESSION[$surveyid]['fieldarray'] as $key => $ia)
            {
                ++$qnumber;
                $ia[9] = $qnumber; // incremental question count;

                if ((isset($ia[10]) && $ia[10] == $gid) || (!isset($ia[10]) && $ia[5] == $gid))
                {
                    if ($surveyMode == 'question' && $ia[0] != $stepInfo['qid'])
                    {
                        continue;
                    }
                    $qidattributes = getQuestionAttributeValues($ia[0], $ia[4]);
                    if ($ia[4] != '*' && ($qidattributes === false || !isset($qidattributes['hidden']) || $qidattributes['hidden'] == 1))
                    {
                        continue;
                    }

                    //Get the answers/inputnames
                    // TMSW - can content of retrieveAnswers() be provided by LEM?  Review scope of what it provides.
                    // TODO - retrieveAnswers is slow - queries database separately for each question. May be fixed in _CI or _YII ports, so ignore for now
                    list($plus_qanda, $plus_inputnames) = retrieveAnswers($ia, $surveyid);
                    if ($plus_qanda)
                    {
                        $plus_qanda[] = $ia[4];
                        $plus_qanda[] = $ia[6]; // adds madatory identifyer for adding mandatory class to question wrapping div
                        $qanda[] = $plus_qanda;
                    }
                    if ($plus_inputnames)
                    {
                        $inputnames = addtoarray_single($inputnames, $plus_inputnames);
                    }

                    //Display the "mandatory" popup if necessary
                    // TMSW - get question-level error messages - don't call **_popup() directly
                    if ($okToShowErrors && $stepInfo['mandViolation'])
                    {
                        list($mandatorypopup, $popup) = mandatory_popup($ia, $notanswered);
                    }

                    //Display the "validation" popup if necessary
                    if ($okToShowErrors && !$stepInfo['valid'])
                    {
                        list($validationpopup, $vpopup) = validation_popup($ia, $notvalidated);
                    }

                    // Display the "file validation" popup if necessary
                    if ($okToShowErrors && isset($filenotvalidated))
                    {
                        list($filevalidationpopup, $fpopup) = file_validation_popup($ia, $filenotvalidated);
                    }
                }
                if ($ia[4] == "|")
                    $upload_file = TRUE;
            } //end iteration
        }

        if ($surveyMode != 'survey' && isset($thissurvey['showprogress']) && $thissurvey['showprogress'] == 'Y')
        {
            if ($show_empty_group)
            {
                $percentcomplete = makegraph($_SESSION[$surveyid]['totalsteps'] + 1, $_SESSION[$surveyid]['totalsteps']);
            }
            else
            {
                $percentcomplete = makegraph($_SESSION[$surveyid]['step'], $_SESSION[$surveyid]['totalsteps']);
            }
        }
        if (!(isset($languagechanger) && strlen($languagechanger) > 0) && function_exists('makelanguagechanger'))
        {
            $languagechanger = makelanguagechanger($thissurvey['language']);
        }

        //READ TEMPLATES, INSERT DATA AND PRESENT PAGE
        sendcacheheaders();
        doHeader();

        if (isset($popup))
        {
            echo $popup;
        }
        if (isset($vpopup))
        {
            echo $vpopup;
        }
        if (isset($fpopup))
        {
            echo $fpopup;
        }

        $redata = compact(array_keys(get_defined_vars()));

        echo templatereplace(file_get_contents("$thistpl/startpage.pstpl"), array(), $redata);

        //ALTER PAGE CLASS TO PROVIDE WHOLE-PAGE ALTERNATION
        if ($surveyMode != 'survey' && $_SESSION[$surveyid]['step'] != $_SESSION[$surveyid]['prevstep'] ||
                (isset($_SESSION[$surveyid]['stepno']) && $_SESSION[$surveyid]['stepno'] % 2))
        {
            if (!isset($_SESSION[$surveyid]['stepno']))
                $_SESSION[$surveyid]['stepno'] = 0;
            if ($_SESSION[$surveyid]['step'] != $_SESSION[$surveyid]['prevstep'])
                ++$_SESSION[$surveyid]['stepno'];
            if ($_SESSION[$surveyid]['stepno'] % 2)
            {
                echo "<script type=\"text/javascript\">\n"
                . "  $(\"body\").addClass(\"page-odd\");\n"
                . "</script>\n";
            }
        }

        $hiddenfieldnames = implode("|", $inputnames);

        if (isset($upload_file) && $upload_file)
            echo "<form enctype=\"multipart/form-data\" method='post' action='" . Yii::app()->getController()->createUrl("survey/index") . "' id='limesurvey' name='limesurvey' autocomplete='off'>
              <!-- INPUT NAMES -->
              <input type='hidden' name='fieldnames' value='{$hiddenfieldnames}' id='fieldnames' />\n";
        else
            echo "<form method='post' action='" . Yii::app()->getController()->createUrl("survey/index") . "' id='limesurvey' name='limesurvey' autocomplete='off'>
              <!-- INPUT NAMES -->
              <input type='hidden' name='fieldnames' value='{$hiddenfieldnames}' id='fieldnames' />\n";
        echo sDefaultSubmitHandler();

        // <-- END FEATURE - SAVE

        if ($surveyMode == 'survey')
        {
            if (isset($thissurvey['showwelcome']) && $thissurvey['showwelcome'] == 'N')
            {
                //Hide the welcome screen if explicitly set
            }
            else
            {
                echo templatereplace(file_get_contents("$thistpl/welcome.pstpl"), array(), $redata) . "\n";
            }

            if ($thissurvey['anonymized'] == "Y")
            {
                echo templatereplace(file_get_contents("$thistpl/privacy.pstpl"), array(), $redata) . "\n";
            }
        }

        // <-- START THE SURVEY -->
        if ($surveyMode != 'survey')
        {
            echo templatereplace(file_get_contents("{$thistpl}/survey.pstpl"), array(), $redata);
        }

        // the runonce element has been changed from a hidden to a text/display:none one
        // in order to workaround an not-reproduced issue #4453 (lemeur)
        echo "<input type='text' id='runonce' value='0' style='display: none;'/>
            <!-- JAVASCRIPT FOR CONDITIONAL QUESTIONS -->
            <script type='text/javascript'>
            <!--\n";

        print <<<END
            function noop_checkconditions(value, name, type)
            {
                checkconditions(value, name, type);
            }

            function checkconditions(value, name, type)
            {
                if (type == 'radio' || type == 'select-one')
                {
                    var hiddenformname='java'+name;
                    document.getElementById(hiddenformname).value=value;
                }

                if (type == 'checkbox')
                {
                    var hiddenformname='java'+name;
                    var chkname='answer'+name;
                    if (document.getElementById(chkname).checked)
                    {
                        document.getElementById(hiddenformname).value='Y';
                    } else
                    {
                        document.getElementById(hiddenformname).value='';
                    }
                }
                ExprMgr_process_relevance_and_tailoring();
            }
        // -->
        </script>
END;

        //Display the "mandatory" message on page if necessary
        if (isset($showpopups) && $showpopups == 0 && $stepInfo['mandViolation'] && $okToShowErrors)
        {
            echo "<p><span class='errormandatory'>" . $clang->gT("One or more mandatory questions have not been answered. You cannot proceed until these have been completed.") . "</span></p>";
        }

        //Display the "validation" message on page if necessary
        if (isset($showpopups) && $showpopups == 0 && !$stepInfo['valid'] && $okToShowErrors)
        {
            echo "<p><span class='errormandatory'>" . $clang->gT("One or more questions have not been answered in a valid manner. You cannot proceed until these answers are valid.") . "</span></p>";
        }

        //Display the "file validation" message on page if necessary
        if (isset($showpopups) && $showpopups == 0 && isset($filenotvalidated) && $filenotvalidated == true && $okToShowErrors)
        {
            echo "<p><span class='errormandatory'>" . $clang->gT("One or more uploaded files are not in proper format/size. You cannot proceed until these files are valid.") . "</span></p>";
        }

        foreach ($_SESSION[$surveyid]['grouplist'] as $gl)
        {
            $gid = $gl[0];
            $groupname = $gl[1];
            $groupdescription = $gl[2];

            if ($surveyMode != 'survey' && $gid != $onlyThisGID)
            {
                continue;
            }

            $redata = compact(array_keys(get_defined_vars()));

            echo "\n\n<!-- START THE GROUP -->\n";
            echo "\n\n<div id='group-$gid'>\n";
            echo templatereplace(file_get_contents("$thistpl/startgroup.pstpl"), array(), $redata);
            echo "\n";

            if ($groupdescription)
            {
                echo templatereplace(file_get_contents("$thistpl/groupdescription.pstpl"), array(), $redata);
            }
            echo "\n";

            echo "\n\n<!-- PRESENT THE QUESTIONS -->\n";

            foreach ($qanda as $qa) // one entry per QID
            {
                $qid = $qa[4];
                $qinfo = LimeExpressionManager::GetQuestionStatus($qid);
                $lastgrouparray = explode("X", $qa[7]);
                $lastgroup = $lastgrouparray[0] . "X" . $lastgrouparray[1]; // id of the last group, derived from question id

                $q_class = question_class($qinfo['info']['type']);

                $man_class = '';
                if ($qinfo['info']['mandatory'] == 'Y')
                {
                    $man_class .= ' mandatory';
                }

                if ($qinfo['anyUnanswered'] && $_SESSION[$surveyid]['maxstep'] != $_SESSION[$surveyid]['step'])
                {
                    $man_class .= ' missing';
                }

                $n_q_display = '';
                if ($qinfo['hidden'] && $qinfo['info']['type'] != '*')
                {
                    continue; // skip this one
                }

                if (!$qinfo['relevant'] || ($qinfo['hidden'] && $qinfo['info']['type'] == '*'))
                {
                    $n_q_display = ' style="display: none;"';
                }

                $question = $qa[0];
                //===================================================================
                // The following four variables offer the templating system the
                // capacity to fully control the HTML output for questions making the
                // above echo redundant if desired.
                $question['essentials'] = 'id="question' . $qa[4] . '"' . $n_q_display;
                $question['class'] = $q_class;
                $question['man_class'] = $man_class;
                $question['code'] = $qa[5];
                $question['sgq'] = $qa[7];
                $question['aid'] = !empty($qinfo['info']['aid']) ? $qinfo['info']['aid'] : 0;
                $question['sqid'] = !empty($qinfo['info']['sqid']) ? $qinfo['info']['sqid'] : 0;
                //===================================================================
                $answer = $qa[1];
                $help = $qinfo['info']['help'];   // $qa[2];

                $redata = compact(array_keys(get_defined_vars()));

                $question_template = file_get_contents($thistpl . '/question.pstpl');
                if (preg_match('/\{QUESTION_ESSENTIALS\}/', $question_template) === false || preg_match('/\{QUESTION_CLASS\}/', $question_template) === false)
                {
                    // if {QUESTION_ESSENTIALS} is present in the template but not {QUESTION_CLASS} remove it because you don't want id="" and display="" duplicated.
                    $question_template = str_replace('{QUESTION_ESSENTIALS}', '', $question_template);
                    $question_template = str_replace('{QUESTION_CLASS}', '', $question_template);
                    echo '
            <!-- NEW QUESTION -->
                        <div id="question' . $qa[4] . '" class="' . $q_class . $man_class . '"' . $n_q_display . '>';
                    echo templatereplace($question_template, array(), $redata, false, false, $qa[4]);
                    echo '</div>';
                }
                else
                {
                    // TMSW - eventually refactor so that only substitutes the QUESTION_** fields - doesn't need full power of template replace
                    // TMSW - also, want to return a string, and call templatereplace once on that result string once all done.
                    echo templatereplace($question_template, array(), $redata, false, false, $qa[4]);
                }
            }
            if ($surveyMode != 'survey')
            {
                echo "<input type='hidden' name='lastgroup' value='$lastgroup' id='lastgroup' />\n"; // for counting the time spent on each group
            }

            echo "\n\n<!-- END THE GROUP -->\n";
            echo templatereplace(file_get_contents("$thistpl/endgroup.pstpl"), array(), $redata);
            echo "\n\n</div>\n";
        }

        LimeExpressionManager::FinishProcessingGroup();
        echo LimeExpressionManager::GetRelevanceAndTailoringJavaScript();
        LimeExpressionManager::FinishProcessingPage();

        if (!$previewgrp)
        {
            $navigator = surveymover(); //This gets globalised in the templatereplace function
            $redata = compact(array_keys(get_defined_vars()));

            echo "\n\n<!-- PRESENT THE NAVIGATOR -->\n";
            echo templatereplace(file_get_contents("$thistpl/navigator.pstpl"), array(), $redata);
            echo "\n";

            if ($thissurvey['active'] != "Y")
            {
                echo "<p style='text-align:center' class='error'>" . $clang->gT("This survey is currently not active. You will not be able to save your responses.") . "</p>\n";
            }


            if ($surveyMode != 'survey' && $thissurvey['allowjumps'] == 'Y')
            {
                echo "\n\n<!-- PRESENT THE INDEX -->\n";

                echo '<div id="index"><div class="container"><h2>' . $clang->gT("Question index") . '</h2>';

                $stepIndex = LimeExpressionManager::GetStepIndexInfo();
                $lastGseq = -1;
                for ($v = 0, $n = 0; $n != $_SESSION[$surveyid]['maxstep']; ++$n)
                {
                    if (!isset($stepIndex[$n]))
                    {
                        continue;   // this is an invalid group - skip it
                    }
                    $stepInfo = $stepIndex[$n];

                    if (!$stepInfo['show'])
                        continue;

                    if ($surveyMode == 'question' && $lastGseq != $stepInfo['gseq'])
                    {
                        // show the group label
                        echo '<h3>' . FlattenText($stepInfo['gname']) . "</h3>";
                        $lastGseq = $stepInfo['gseq'];
                    }

                    $sText = (($surveyMode == 'group') ? FlattenText($stepInfo['gname'] . ': ' . $stepInfo['gtext']) : FlattenText($stepInfo['qtext']));
                    $bGAnsw = !$stepInfo['anyUnanswered'];

                    ++$v;

                    $class = ($n == $_SESSION[$surveyid]['step'] - 1 ? 'current' : ($bGAnsw ? 'answer' : 'missing'));
                    if ($v % 2)
                        $class .= " odd";

                    $s = $n + 1;
                    echo "<div class=\"row $class\" onclick=\"javascript:document.limesurvey.move.value = '$s'; document.limesurvey.submit();\"><span class=\"hdr\">$v</span><span title=\"$sText\">$sText</span></div>";
                }

                if ($_SESSION[$surveyid]['maxstep'] == $_SESSION[$surveyid]['totalsteps'])
                {
                    echo "<input class='submit' type='submit' accesskey='l' onclick=\"javascript:document.limesurvey.move.value = 'movesubmit';\" value=' "
                    . $clang->gT("Submit") . " ' name='move2' />\n";
                }

                echo '</div></div>';
                /* Can be replaced by php or in global js */
                echo "<script type=\"text/javascript\">\n"
                . "  $(\".outerframe\").addClass(\"withindex\");\n"
                . "  var idx = $(\"#index\");\n"
                . "  var row = $(\"#index .row.current\");\n"
                . "  idx.scrollTop(row.position().top - idx.height() / 2 - row.height() / 2);\n"
                . "</script>\n";
                echo "\n";
            }

            echo "<input type='hidden' name='thisstep' value='{$_SESSION[$surveyid]['step']}' id='thisstep' />\n";
            echo "<input type='hidden' name='sid' value='$surveyid' id='sid' />\n";
            echo "<input type='hidden' name='start_time' value='" . time() . "' id='start_time' />\n";
            $_SESSION[$surveyid]['LEMpostKey'] = mt_rand();
            echo "<input type='hidden' name='LEMpostKey' value='{$_SESSION[$surveyid]['LEMpostKey']}' id='LEMpostKey' />\n";

            if (isset($token) && !empty($token))
            {
                echo "\n<input type='hidden' name='token' value='$token' id='token' />\n";
            }
        }

        if (($LEMdebugLevel & LEM_DEBUG_TIMING) == LEM_DEBUG_TIMING)
        {
            echo LimeExpressionManager::GetDebugTimingMessage();
        }
        if (($LEMdebugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY)
        {
            echo "<table><tr><td align='left'><b>Group/Question Validation Results:</b>" . $moveResult['message'] . "</td></tr></table>\n";
        }
        echo "</form>\n";

        echo templatereplace(file_get_contents("$thistpl/endpage.pstpl"), array(), $redata);

        echo "\n";

        doFooter();

	}
}
