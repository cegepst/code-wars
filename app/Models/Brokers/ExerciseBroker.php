<?php namespace Models\Brokers;

use Models\Services\NotificationService;
use stdClass;
use Zephyrus\Application\Flash;

class ExerciseBroker extends Broker
{
    public function findByID($id): ?stdClass
    {
        $sql = "SELECT e.id, week_id, name, description, cash_reward, difficulty, execution_exemple, point_reward, w.is_active
                FROM codewars.exercise e join codewars.week w on w.id = e.week_id
                WHERE e.id = ?";
        return $this->selectSingle($sql, [$id]);
    }

    public function getAll(): array
    {
        $sql = "SELECT e.id, e.difficulty, e.name, e.description, e.cash_reward, e.point_reward, e.execution_exemple, w.id as week_id, w.number, w.is_active, w.start_date
                FROM codewars.week w join codewars.exercise e on w.id = e.week_id
                ORDER BY e.week_id";
        return $this->select($sql);
    }

    public function getAllActive(): array
    {
        $sql = "SELECT e.id, e.difficulty, e.name, e.description, e.cash_reward, e.point_reward, e.execution_exemple, w.id as week_id, w.number, w.is_active, w.start_date, se.corrected, se.completed
                FROM codewars.exercise e join codewars.week w on w.id = e.week_id left join codewars.studentexercise se on se.exercise_id = e.id
                WHERE w.is_active = true
                ORDER BY e.week_id, se.corrected desc, se.completed";
        return $this->select($sql);
    }

    public function getAllByWeek($weekId): array
    {
        $sql = "SELECT e.id, e.difficulty, e.name, e.description, e.cash_reward, e.point_reward, e.execution_exemple 
                FROM codewars.exercise e join codewars.week w on w.id = e.week_id where e.week_id = ?";
        return $this->select($sql, [$weekId]);
    }

    public function insert($name, $difficulty, $description, $exemple, $cash, $point, $weekId): int
    {
        $sql = "INSERT INTO codewars.exercise (id, name, difficulty, description, execution_exemple, cash_reward, point_reward, week_id) VALUES (default, ?, ?, ?, ?, ?, ?, ?) RETURNING id";

        $result = $this->selectSingle($sql, [
            ucfirst($name),
            $difficulty,
            $description,
            $exemple,
            $cash,
            $point,
            $weekId
        ]);
        return $result->id;
    }

    public function submitExercise($student, $exerciseId, $path, $fileName, $comment)
    {
        $sql = "insert into codewars.studentexercise(id, student_da, exercise_id, completed, corrected, comments, dir_path, submit_date, student_comment) values (default, ?, ?, true, false, null, ?, now(), ?)";
        $this->query($sql, [$student->da, $exerciseId, $path, $comment]);
        NotificationService::newCorrectionAvailable($student, $fileName);
    }

    public function deleteExercise($studentDA, $exerciseId)
    {
        $sql = "DELETE FROM codewars.studentexercise WHERE student_da = ? AND exercise_id = ?";
        return $this->query($sql, [$studentDA, $exerciseId]);
    }

    public function getExerciseByStudentDA($studentDA, $exerciseId): ?stdClass
    {
        $sql = "SELECT * FROM codewars.studentexercise se WHERE student_da = ? AND exercise_id = ?";
        return $this->selectSingle($sql, [$studentDA, $exerciseId]);
    }

    public function updateSubmit($student, $exerciseId, $path)
    {
        $sql = "update codewars.studentexercise se set dir_path = ?, is_good = null where se.exercise_id = ? and se.student_da = ? and se.completed = true";
        $this->query($sql, [$path, $student->da, $exerciseId]);
        NotificationService::newCorrectionAvailable($student, $this->findByID($exerciseId)->name);
    }

    public function correctExercise($userId, $student, $id, $comment = null)
    {
        $sql = "update codewars.studentexercise se set corrected = true, comments = ?, is_good = true where se.id = ? and se.student_da = ? and se.completed = true";
        $this->query($sql, [$comment, $id, $student->da]);
        $sql = "select e.id as exercise_id, cash_reward, point_reward, e.name from codewars.exercise e join codewars.studentexercise s on e.id = s.exercise_id where s.id = ?";
        $reward = $this->selectSingle($sql, [$id]);
        $broker = new StudentBroker();
        $isPointsPositive = $reward->point_reward >= 0;
        $isCashPositive = $reward->cash_reward >= 0;
        if ($comment == "") {
            $comment = '<p>Correction de la mission : <a href="/exercises/' . $reward->exercise_id . '">' . $reward->name . '</a></p>';
        }
        (new TransactionBroker())->insert($student->id, $comment, $reward->cash_reward, $reward->point_reward, $isCashPositive, $isPointsPositive);
        $broker->addCash($student->da, $reward->cash_reward);
        $broker->addPoints($student->da, $reward->point_reward);
        if ($comment != null) {
            NotificationService::newCommentOnCorrection($userId, $reward->name);
        }
        NotificationService::exerciseCorrected($userId, $reward->cash_reward, $reward->point_reward);
    }

    public function incorrectExercise($userId, $student, $id, $comment)
    {
        $sql = "update codewars.studentexercise se set comments = ?, is_good = false where se.id = ? and se.student_da = ? and se.completed = true";
        $this->query($sql, [$comment, $id, $student->da]);
        $exercise = (new StudentExerciseBroker())->findById($id);
        NotificationService::incorrectSolution($userId, $exercise->name, $exercise->se_id);
    }

    public function delete($id)
    {
        $this->deleteStudentExercisesOf($id);
        $sql = "DELETE FROM codewars.exercise WHERE id = ?;";
        return $this->query($sql, [$id]);
    }

    public function update($id, $name, $difficulty, $description, $exemple, $cash, $point, $weekId)
    {
        $sql = "UPDATE codewars.exercise SET name = ?, difficulty = ?, description = ?, execution_exemple = ?, cash_reward = ?, point_reward = ?, week_id = ? WHERE id = ?";
        $this->query($sql, [$name, $difficulty, $description, $exemple, $cash, $point, $weekId, $id]);
    }

    public function isSubmitted($id, $da): bool
    {
        $sql = "select * from codewars.studentexercise se where se.exercise_id = ? and se.student_da = ? and se.completed = true";
        return $this->selectSingle($sql, [$id, $da]) != null;
    }

    public function isCorrected($id, $da): bool
    {
        $sql = "select * from codewars.studentexercise se where se.exercise_id = ? and se.student_da = ? and se.completed = true and se.corrected = true";
        return $this->selectSingle($sql, [$id, $da]) != null;
    }

    public function isGood($id, $da): bool
    {
        $sql = "select * from codewars.studentexercise se where se.exercise_id = ? and se.student_da = ? and se.is_good = false";
        return $this->selectSingle($sql, [$id, $da]) != null;
    }

    public function getCorrection(): array
    {
        $sql = "select se.id, se.dir_path, se.submit_date, e.id as exercise_id, e.name, se.student_comment, s.da as student_da, p.firstname, p.lastname from codewars.studentexercise se join codewars.exercise e on e.id = se.exercise_id join codewars.student s on s.da = se.student_da join codewars.user u on u.da = s.da join codewars.person p on p.da = u.da where se.completed = true and se.corrected = false and is_good is null order by se.submit_date";
        return $this->select($sql);
    }

    public function getCorrectionPath($id): ?stdClass
    {
        $sql = "select dir_path as path, e.name as name from codewars.studentexercise se join codewars.exercise e on e.id = se.exercise_id where se.id = ?";
        return $this->selectSingle($sql, [$id]);
    }

    public function getDifficulties()
    {
        $sql = "select t.typname as name, e.enumlabel as value from pg_type t join pg_enum e on t.oid = e.enumtypid order by value desc";
        return $this->select($sql);
    }

    public function deleteStudentExercisesOf($id)
    {
        $sql = "select dir_path from codewars.studentexercise se where se.exercise_id = ?";
        $exerciseDirPath = $this->select($sql, [$id]);
        foreach ($exerciseDirPath as $path) {
            try {
                unlink($path->dir_path);
            } catch (\Exception $e) {
                Flash::error($e);
            }
        }
        $sql = "delete from codewars.studentexercise se where se.exercise_id = ?";
        $this->query($sql, [$id]);
    }

    public function deleteAllFor($da)
    {
        $sql = "select dir_path from codewars.studentexercise se where se.student_da = ?";
        $exerciseDirPath = $this->select($sql, [$da]);
        foreach ($exerciseDirPath as $path) {
            try {
                unlink($path->dir_path);
            } catch (\Exception $e) {
                Flash::error($e);
            }
        }
        $sql = "delete from codewars.studentexercise se where se.student_da = ?";
        $this->query($sql, [$da]);
    }
}
