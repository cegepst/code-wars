<?php namespace Models\Brokers;

use stdClass;

class StudentBroker extends Broker
{

    public function findByDa($da) : ?stdClass
    {
        $sql = "SELECT s.da, s.team_id, t.name team_name, s.cash, s.points, p.username, p.firstname, p.lastname, p.email from codewars.student s 
                join codewars.user u on s.da = u.da
                join codewars.person p on u.da = p.da
                join codewars.team t on t.id = s.team_id
                WHERE s.da = ?";
        return $this->selectSingle($sql, [$da]);
    }

    public function getPoints($da): int
    {
        $sql = "select s.points from codewars.student s where s.da = ?";
        return $this->selectSingle($sql, [$da])->points;
    }

    public function getProgression($da): array
    {
        $sql = "select count(e.id) done from codewars.student s join codewars.studentexercise se on s.da = se.student_da join codewars.exercise e on e.id = se.exercise_id join codewars.week w on e.week_id = w.id where s.da = ? and se.completed = true";
        $done = $this->selectSingle($sql, [$da])->done;
        $nbExercises = Count((new ExerciseBroker())->getAll());
        $totalDone = ($done / $nbExercises) * 100;
        return ["totalDone" => $totalDone, "nbExercicesTotal" => $nbExercises, "nbExercisesDone" => $done];
    }

    public function getProgressionByWeek($da): array
    {
        $sql = "select w.id, w.number, w.start_date, count(e.id) done from codewars.student s join codewars.studentexercise se on s.da = se.student_da join codewars.exercise e on e.id = se.exercise_id join codewars.week w on e.week_id = w.id where s.da = ? and se.completed = true group by w.id";
        $weeks = $this->select($sql, [$da]);
        $broker = new ExerciseBroker();
        foreach ($weeks as $week) {
            $week->progress = number_format(($week->done / Count($broker->getAllByWeek($week->id))) * 100, 0);
        }
        return $weeks;
    }

    public function getExerciseDone($da): int
    {
        $sql = "select count(e.id) done from codewars.student s join codewars.studentexercise se on s.da = se.student_da join codewars.exercise e on e.id = se.exercise_id where s.da = ? and se.completed = true";
        return $this->selectSingle($sql, [$da])->done;
    }

    public function getAll()
    {
        $sql = "SELECT s.da, s.team_id, s.cash, s.points, p.username, p.firstname, p.lastname, t.name as team_name, p.email  from codewars.student s 
                join codewars.user u on s.da = u.da
                join codewars.person p on u.da = p.da
				join codewars.team t on s.team_id = t.id
                ORDER BY s.da";
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
        $sql = "UPDATE codewars.student SET team_id = ?, cash = ?, points = ? WHERE da = ?";
        $this->query($sql, [$team_id, $cash, $points, $da]);
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
        $sql = "SELECT s.da, s.team_id, s.cash, s.points, p.username, p.firstname, p.lastname, t.name as team_name from codewars.student s  join codewars.user u on s.da = u.da join codewars.person p on u.da = p.da join codewars.team t on s.team_id = t.id where t.id = ? order by s.cash desc";
        return $this->select($sql, [$teamId]);
    }

    public function addCash($da, $amount)
    {
        $student = $this->findByDa($da);
        $student->cash += $amount;
        $sql = "UPDATE codewars.student SET cash = ? WHERE da = ?";
        $this->query($sql, [$student->cash, $da]);
    }

    public function addPoints($da, $amount)
    {
        $student = $this->findByDa($da);
        $student->points += $amount;
        $sql = "UPDATE codewars.student SET points = ? WHERE da = ?";
        $this->query($sql, [$student->points, $da]);
    }
}