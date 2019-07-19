<?php

namespace Foodsharing\Modules\Quiz;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;

class QuizGateway extends BaseGateway
{
	public function getQuizzes(): array
	{
		return $this->db->fetchAll('
			SELECT id, name
			FROM fs_quiz
			ORDER BY id
		');
	}

	public function addQuiz(string $name, string $desc, int $maxfp, int $questcount): int
	{
		return $this->db->insert('fs_quiz',
			[
				'name' => $name,
				'desc' => $desc,
				'maxfp' => $maxfp,
				'questcount' => $questcount
			]
		);
	}

	public function updateQuiz(int $id, string $name, string $desc, string $maxfp, string $questcount): int
	{
		return $this->db->update(
			'fs_quiz',
			[
				'name' => $name,
				'desc' => $desc,
				'maxfp' => $maxfp,
				'questcount' => $questcount
			],
			['id' => $id]
		);
	}

	public function getQuiz(int $id): array
	{
		return $this->db->fetchByCriteria(
			'fs_quiz',
			['id', 'name', 'desc', 'maxfp', 'questcount'],
			['id' => $id]
		);
	}

	public function getQuizStatus(int $quizId, int $fsId): array
	{
		$out = array(
			'cleared' => 0,
			'running' => 0,
			'failed' => 0,
			'last_try' => 0,
			'times' => 0
		);

		$res = $this->db->fetchAll('
			SELECT foodsaver_id, `status`, UNIX_TIMESTAMP(`time_start`) AS time_ts
			FROM fs_quiz_session
			WHERE foodsaver_id = :fsId
			AND quiz_id = :quizId
		', ['fsId' => $fsId, 'quizId' => $quizId]);
		if ($res) {
			foreach ($res as $r) {
				++$out['times'];
				if ($r['time_ts'] > $out['last_try']) {
					$out['last_try'] = $r['time_ts'];
				}

				if ($r['status'] == SessionStatus::RUNNING) {
					++$out['running'];
				} elseif ($r['status'] == SessionStatus::PASSED) {
					++$out['cleared'];
				} elseif ($r['status'] == SessionStatus::FAILED) {
					++$out['failed'];
				}
			}
		}

		return $out;
	}

	public function initQuizSession($fsId, $quiz_id, $questions, $maxfp, $questcount, $easymode = 0)
	{
		$questions = serialize($questions);

		return $this->db->insert('fs_quiz_session',
			[
				'foodsaver_id' => $fsId,
				'quiz_id' => $quiz_id,
				'status' => SessionStatus::RUNNING,
				'quiz_index' => 0,
				'quiz_questions' => $questions,
				'time_start' => $this->db->now(),
				'fp' => 0,
				'maxfp' => $maxfp,
				'quest_count' => $questcount,
				'easymode' => $easymode
				]);
	}

	public function getSessions($quizId): array
	{
		return $this->db->fetchAll('
				SELECT
					s.id,
					MAX(s.time_start) AS time_start,
					MIN(s.`status`) AS min_status,
					MAX(s.`status`) AS max_status,
					MIN(s.`fp`) AS min_fp,
					MAX(s.`fp`) AS max_fp,
					UNIX_TIMESTAMP(MAX(s.time_start)) AS time_start_ts,
					CONCAT(fs.name," ",fs.nachname) AS fs_name,
					fs.photo AS fs_photo,
					fs.id AS fs_id,
					count(s.foodsaver_id) AS trycount

				FROM
					fs_quiz_session s
						LEFT JOIN fs_foodsaver fs
						ON s.foodsaver_id = fs.id

				WHERE
					s.quiz_id = :quizId

				GROUP BY
					s.foodsaver_id

				ORDER BY
					time_start DESC
			', [':quizId' => $quizId]);
	}

	public function getUserSessions(int $fsId): array
	{
		return $this->db->fetchAll('
			SELECT
				s.id,
				s.fp,
				s.status,
				s.time_start,
				UNIX_TIMESTAMP(s.time_start) AS time_start_ts,
				q.name AS quiz_name,
				q.id AS quiz_id

			FROM
				fs_quiz_session s
					LEFT JOIN fs_quiz q
					ON s.quiz_id = q.id

			WHERE
				s.foodsaver_id = :fsId

			ORDER BY
				q.id, s.time_start DESC
		', [':fsId' => $fsId]);
	}

	public function getExistingSession(int $quizId, int $fsId)
	{
		$session = $this->db->fetch('
			SELECT
				id,
				quiz_index,
				quiz_questions,
				easymode

			FROM
				fs_quiz_session

			WHERE
				quiz_id = :quizId
			AND
				foodsaver_id = :fsId
			AND
				status = :status
		', [
			'quizId' => $quizId,
			'fsId' => $fsId,
			'status' => SessionStatus::RUNNING
		]);
		if ($session) {
			$session['quiz_questions'] = unserialize($session['quiz_questions']);

			return $session;
		} else {
			return null;
		}
	}

	public function updateQuizSession(int $session_id, string $questions, int $quiz_index): int
	{
		$questions = serialize($questions);

		$this->db->update(
			'fs_quiz_session',
			[
				'quiz_questions' => $questions,
				'quiz_index' => $quiz_index
			],
			['id' => $session_id]
		);
	}

	public function abortSession(int $sid, int $fsId): int
	{
		return $this->db->update(
			'fs_quiz_session',
			['status' => SessionStatus::FAILED],
			[
				'id' => $sid,
				'foodsaver_id' => $fsId
			]
		);
	}

	public function deleteSession(int $id): int
	{
		return $this->db->delete('fs_quiz_session', ['id' => $id], 1);
	}

	public function countPassedQuizSessions(int $fs_id, int $quiz_id): int
	{
		return $this->countQuizSessions($fs_id, $quiz_id, SessionStatus::PASSED);
	}

	public function countQuizSessions(int $fs_id, int $quiz_id, int $status): int
	{
		return $this->db->count('fs_quiz_session', [
			'foodsaver_id' => $fs_id,
			'quiz_id' => $quiz_id,
			'status' => $status
		]);
	}

	public function setRole($fs_id, $quiz_rolle)
	{
		$this->db->update(
			'fs_foodsaver',
			['quiz_rolle' => $quiz_rolle],
			['id' => $fs_id]
		);
	}
}
