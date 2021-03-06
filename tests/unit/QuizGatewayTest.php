<?php

use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizStatus;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;

class QuizGatewayTest extends \Codeception\Test\Unit
{
	protected $tester;

	private $gateway;

	private $foodsharer;
	private $foodsaver;

	protected function _before()
	{
		$this->gateway = $this->tester->get(\Foodsharing\Modules\Quiz\QuizGateway::class);

		$this->foodsharer = $this->tester->createFoodsharer();
		$this->foodsaver = $this->tester->createFoodsaver();

		foreach (range(1, 3) as $quizId) {
			$this->tester->createQuiz($quizId);
		}
	}

	public function testGetQuizzes()
	{
		$quizzes = $this->gateway->listQuiz();
		$this->assertEquals('1', $quizzes[0]['id']);
		$this->assertEquals('2', $quizzes[1]['id']);
		$this->assertEquals('3', $quizzes[2]['id']);
	}

	public function testAddQuestion()
	{
		$questionId = $this->gateway->addQuestion(1, 'question text', 3, 60);
		$this->tester->seeInDatabase('fs_question', ['text' => 'question text']);
		$this->tester->seeInDatabase('fs_question_has_quiz', ['question_id' => $questionId, 'quiz_id' => 1]);
	}

	public function testDeleteQuestion()
	{
		$this->tester->haveInDatabase('fs_question', ['id' => 1]);
		$this->tester->haveInDatabase('fs_question_has_quiz', ['quiz_id' => 1, 'question_id' => 1]);
		$this->tester->haveInDatabase('fs_answer', ['question_id' => 1]);

		$this->gateway->deleteQuestion(1);

		$this->tester->dontSeeInDatabase('fs_question', ['id' => 1]);
		$this->tester->dontSeeInDatabase('fs_question_has_quiz', ['question_id' => 1]);
		$this->tester->dontSeeInDatabase('fs_answer', ['question_id' => 1]);
	}

	public function testFoodsharerHasNeverTriedQuiz()
	{
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::NEVER_TRIED, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	public function testFoodsharerHasRunningQuizSession()
	{
		$this->foodsharerTriesQuiz(SessionStatus::RUNNING);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::RUNNING, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	public function testFoodsharerHasPassedQuiz()
	{
		$this->foodsharerTriesQuiz(SessionStatus::PASSED);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::PASSED, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	public function testFoodsharerFailedQuizOnce()
	{
		$this->foodsharerTriesQuiz(SessionStatus::FAILED);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::FAILED, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	public function testFoodsharerFailedTwice()
	{
		$this->foodsharerTriesQuiz(SessionStatus::FAILED, 2);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::FAILED, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	public function testFoodsharerIsPaused()
	{
		$this->foodsharerTriesQuiz(SessionStatus::FAILED, 3);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::PAUSE, $quizStatus['status']);
		$this->tester->assertEquals(30, $quizStatus['wait']);
	}

	public function testFoodsharerIsPausedForOneMoreDay()
	{
		$this->foodsharerTriesQuiz(SessionStatus::FAILED, 3, 29);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::PAUSE, $quizStatus['status']);
		$this->tester->assertEquals(1, $quizStatus['wait']);
	}

	public function testFoodsharerHasAForthTry()
	{
		$this->foodsharerTriesQuiz(SessionStatus::FAILED, 3, 30);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::PAUSE_ELAPSED, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	public function testFoodsharerHasAFifthTry()
	{
		$this->foodsharerTriesQuiz(SessionStatus::FAILED, 4);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::PAUSE_ELAPSED, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	public function testFoodsharerGetsDisqualifiedAfterFifthFailure()
	{
		$this->foodsharerTriesQuiz(SessionStatus::FAILED, 5);
		$quizStatus = $this->foodsharerQuizStatus();
		$this->tester->assertEquals(QuizStatus::DISQUALIFIED, $quizStatus['status']);
		$this->tester->assertEquals(0, $quizStatus['wait']);
	}

	private function foodsharerTriesQuiz(int $status, int $times = 1, int $daysAgo = 0): void
	{
		foreach (range(1, $times) as $i) {
			$this->tester->createQuizTry($this->foodsharer['id'], Role::FOODSAVER, $status, $daysAgo);
		}
	}

	private function foodsharerQuizStatus(): array
	{
		return $this->gateway->getQuizStatus(Role::FOODSAVER, $this->foodsharer['id']);
	}
}
