<?php namespace Models\Brokers;

use Models\Services\NotificationService;
use stdClass;

class StudentBroker extends Broker
{
    public function findByDa($da): ?stdClass
    {
        $sql = "SELECT s.id, s.da, s.team_id, t.name team_name, s.cash, s.points, p.username, p.firstname, p.lastname, p.email 
                from codewars.student s 
                join codewars.user u on s.da = u.da
                join codewars.person p on u.da = p.da
                join codewars.team t on t.id = s.team_id
                WHERE s.da = ?";
        return $this->selectSingle($sql, [$da]);
    }

    public function getCash($da): int
    {
        $sql = "select s.cash from codewars.student s where s.da = ?";
        return $this->selectSingle($sql, [$da])->cash;
    }

    public function getPoints($da): int
    {
        $sql = "select s.points from codewars.student s where s.da = ?";
        return $this->selectSingle($sql, [$da])->points;
    }

    public function getProgression($da): array
    {
        $sql = "select count(e.id) done from codewars.student s join codewars.studentexercise se on s.da = se.student_da join codewars.exercise e on e.id = se.exercise_id join codewars.week w on e.week_id = w.id 
                where s.da = ? and se.corrected = true and w.is_active = true";
        $done = $this->selectSingle($sql, [$da])->done;
        $nbExercises = count((new ExerciseBroker())->getAllActive());
        $totalDone = 0;
        if ($nbExercises != 0) {
            $totalDone = ($done / $nbExercises) * 100;
        }
        return ["totalDone" => $totalDone, "nbExercicesTotal" => $nbExercises, "nbExercisesDone" => $done];
    }

    public function getProgressionByWeek($da): array
    {
        $sql = "select w.id as week_id, w.number, w.start_date, count(e.id) done from codewars.student s join codewars.studentexercise se on s.da = se.student_da join codewars.exercise e on e.id = se.exercise_id join codewars.week w on e.week_id = w.id
                where s.da = ? and se.corrected = true group by w.id";
        $exercisesPerWeeks = $this->select($sql, [$da]);
        $weeks = (new WeekBroker())->getAllActive();
        $exercisesBroker = new ExerciseBroker();
        $index = 0;
        foreach ($weeks as $week) {
            $week->progress = 0;
            $week->nbExercise = !empty($exercisesBroker->getAllByWeek($week->week_id)) ? $exercisesBroker->getAllByWeek($week->week_id) : "N/A";
            if (isset($exercisesPerWeeks[$index])) {
                if ($exercisesPerWeeks[$index]->week_id == $week->week_id) {
                    $week->progress = number_format(($exercisesPerWeeks[$index]->done / count($exercisesBroker->getAllByWeek($week->week_id))) * 100, 0);
                    $index++;
                }
            }
        }

        return $weeks;
    }

    public function getExerciseDone($da): int
    {
        $sql = "select count(e.id) done from codewars.student s join codewars.studentexercise se on s.da = se.student_da join codewars.exercise e on e.id = se.exercise_id join codewars.week w on e.week_id = w.id where s.da = ? and se.corrected = true and w.is_active = true";
        return $this->selectSingle($sql, [$da])->done;
    }

    public function getAllAlphabetic()
    {
        $sql = "SELECT s.id, s.da, s.team_id, s.cash, s.points, p.username, p.firstname, p.lastname, t.name as team_name, p.email  from codewars.student s 
                join codewars.user u on s.da = u.da
                join codewars.person p on u.da = p.da
				join codewars.team t on s.team_id = t.id
                ORDER BY p.firstname, p.lastname";
        return $this->select($sql);
    }

    public function getAll()
    {
        $sql = "SELECT s.id, s.da, s.team_id, s.cash, s.points, p.username, p.firstname, p.lastname, t.name as team_name, p.email  from codewars.student s 
                join codewars.user u on s.da = u.da
                join codewars.person p on u.da = p.da
				join codewars.team t on s.team_id = t.id
                ORDER BY s.points desc, s.cash desc";
        return $this->select($sql);
    }

    public function insert($da, $team_id, $cash, $points)
    {
        $sql = "INSERT INTO codewars.student (da, team_id, cash, points) VALUES (?, ?, ?, ?)";
        $this->query($sql, [$da, $team_id, $cash, $points]);
    }

    public function delete($da)
    {
        $sql = "DELETE FROM codewars.student WHERE da = ?;";
        return $this->query($sql, [$da]);
    }

    public function update($da, $team_id, $cash, $points)
    {
        $notifyCash = $this->isCashDifferent($da, $cash);
        $notifyPoints = $this->isPointsDifferent($da, $points);
        $addedCash = $this->getAddedCash($da, $cash);
        $addedPoints = $this->getAddedPoints($da, $points);
        $sql = "UPDATE codewars.student SET team_id = ?, cash = ?, points = ? WHERE da = ?";
        $this->query($sql, [$team_id, $cash, $points, $da]);
        if ($notifyCash) {
            NotificationService::newBalance($this->getStudentId($da), $addedCash, $this->getCash($da));
        }
        if ($notifyPoints) {
            NotificationService::newPoints($this->getStudentId($da), $addedPoints, $this->getPoints($da));
        }
    }

    public function hasItem($da): bool
    {
        $sql = "SELECT s.da, s.team_id from codewars.student s 
                join codewars.user u on s.da = u.da
                join codewars.studentitem si on s.da = si.student_da
                WHERE s.da = ?";
        return $this->selectSingle($sql, [$da]) != null;
    }

    public function sameTeamStudent($teamId): array
    {
        $sql = "SELECT s.da, s.team_id, s.cash, s.points, p.username, p.firstname, p.lastname, t.name as team_name from codewars.student s  join codewars.user u on s.da = u.da join codewars.person p on u.da = p.da join codewars.team t on s.team_id = t.id where t.id = ? order by s.points desc, s.cash desc";
        return $this->select($sql, [$teamId]);
    }

    public function addCash($da, $amount)
    {
        $student = $this->findByDa($da);
        $cash = $student->cash + $amount;
        if ($cash < 0) {
            $cash = 0;
        }
        $sql = "UPDATE codewars.student SET cash = ? WHERE da = ?";
        $this->query($sql, [$cash, $da]);
    }

    public function addPoints($da, $amount)
    {
        $student = $this->findByDa($da);
        $points = $student->points + $amount;
        if ($points < 0) {
            $points = 0;
        }
        $sql = "UPDATE codewars.student SET points = ? WHERE da = ?";
        $this->query($sql, [$points, $da]);
    }

    private function getStudentId($da)
    {
        $sql = "SELECT s.da, u.id FROM codewars.student s 
                    JOIN codewars.user u on s.da = u.da 
                    WHERE s.da = ?";
        $result = $this->selectSingle($sql, [$da]);
        return $result->id;
    }

    private function isCashDifferent($da, $cash): bool
    {
        $sql = "SELECT s.da, s.cash FROM codewars.student s WHERE s.da = ?";
        $result = $this->selectSingle($sql, [$da]);
        return $result->cash != $cash;
    }

    private function isPointsDifferent($da, $points): bool
    {
        $sql = "SELECT s.da, s.points FROM codewars.student s WHERE s.da = ?";
        $result = $this->selectSingle($sql, [$da]);
        return $result->points != $points;
    }

    private function getAddedCash($da, $cash)
    {
        $sql = "SELECT s.da, s.cash FROM codewars.student s WHERE s.da = ?";
        $result = $this->selectSingle($sql, [$da]);
        return $cash - $result->cash;
    }

    private function getAddedPoints($da, $points)
    {
        $sql = "SELECT s.da, s.points FROM codewars.student s WHERE s.da = ?";
        $result = $this->selectSingle($sql, [$da]);
        return $points - $result->points;
    }
}
