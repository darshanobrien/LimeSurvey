<?php
namespace LimeSurvey\tests\acceptance\question;

use LimeSurvey\tests\TestBaseClass;
use LimeSurvey\tests\DummyController;

/**
 *  LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */



/**
 * @since 2017-06-13
 * @group dateforward
 */
class DateTimeForwardBackTest extends TestBaseClass
{

    /**
     * Import survey in tests/surveys/.
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        $_POST = [];
        $_SESSION = [];

        $surveyFile = self::$surveysFolder.'/limesurvey_survey_917744.lss';
        self::importSurvey($surveyFile);
    }

    /**
     * q1 is hidden question with default answer "now".
     * @group q01
     */
    public function testQ1()
    {
        list($question, $group, $sgqa) = self::$testHelper->getSgqa('G1Q00001', self::$surveyId);
        $surveyMode = 'group';
        $LEMdebugLevel = 0;

        self::$testHelper->activateSurvey(self::$surveyId);

        // Must fetch this AFTER survey is activated.
        $surveyOptions = self::$testHelper->getSurveyOptions(self::$surveyId);

        \Yii::app()->setConfig('surveyID', self::$surveyId);
        \Yii::app()->setController(new DummyController('dummyid'));
        \buildsurveysession(self::$surveyId);
        $result = \LimeExpressionManager::StartSurvey(
            self::$surveyId,
            $surveyMode,
            $surveyOptions,
            false,
            $LEMdebugLevel
        );
        $this->assertEquals(
            [
                'hasNext' => 1,
                'hasPrevious' => null
            ],
            $result
        );

        $qid = $question->qid;
        $gseq = 0;
        $_POST['relevance' . $qid] = 1;
        $_POST['relevanceG' . $gseq] = 1;
        $_POST['lastgroup'] = self::$surveyId . 'X' . $group->gid;
        $_POST['movenext'] = 'movenext';
        $_POST['thisstep'] = 1;
        $_POST['sid'] = self::$surveyId;
        $_POST[$sgqa] = '10:00';
        $_SESSION['survey_' . self::$surveyId]['maxstep'] = 2;
        $_SESSION['survey_' . self::$surveyId]['step'] = 1;

        $moveResult = \LimeExpressionManager::NavigateForwards();
        $result = \LimeExpressionManager::ProcessCurrentResponses();
        $this->assertEquals($result[$sgqa]['value'], '1970-01-01 10:00');

        $moveResult = \LimeExpressionManager::NavigateForwards();
        // Result is empty dummy text question.
        \LimeExpressionManager::ProcessCurrentResponses();

        // Check answer in database.
        $query = 'SELECT * FROM {{survey_' . self::$surveyId . '}}';
        $result = \Yii::app()->db->createCommand($query)->queryAll();
        $this->assertEquals($result[0][$sgqa], '1970-01-01 10:00:00', 'Answer in database is 10:00');

        // Check result from qanda.
        $qanda = \retrieveAnswers(
            $_SESSION['survey_' . self::$surveyId]['fieldarray'][0],
            self::$surveyId
        );
        $this->assertEquals(false, strpos($qanda[0][1], "value=\"11:00\""), 'No 11:00 value from qanda');
        $this->assertNotEquals(false, strpos($qanda[0][1], "value=\"10:00\""), 'One 10:00 value from qanda');

        self::$testHelper->deactivateSurvey(self::$surveyId);
    }
}