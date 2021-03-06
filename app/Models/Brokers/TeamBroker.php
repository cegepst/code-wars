<?php namespace Models\Brokers;

class TeamBroker extends Broker
{
    public function getAll()
    {
        $sql = "SELECT * FROM codewars.team";
        return $this->select($sql);
    }

    public function findById($id)
    {
        $sql = "SELECT * FROM codewars.team WHERE id = ?";
        return $this->selectSingle($sql, [$id]);
    }

    public function getIdByName($name): int
    {
        $sql = "SELECT id FROM codewars.team WHERE name = ?";
        return $this->selectSingle($sql, [$name])->id;
    }

    public function findAllStudentByTeam($id): array
    {
        $sql = "select * from codewars.team join codewars.student s on team.id = s.team_id join codewars.person p on p.da = s.da where s.team_id = ? order by s.points desc, s.cash desc";
        return $this->select($sql, [$id]);
    }

    public function addToTeam($id, $points, $cash, $reason)
    {
        $students = $this->findAllStudentByTeam($id);
        $studentBroker = new StudentBroker();
        $transactionBroker = new TransactionBroker();
        foreach ($students as $student) {
            $studentBroker->addPoints($student->da, $points);
            $studentBroker->addCash($student->da, $cash);
            $isPointsPositive = $points >= 0;
            $isCashPositive = $cash >= 0;
            if ($reason == "") {
                $reason = "Votre équipe a reçu une récompense.";
            }
            (new TransactionBroker())->insert($student->id, $reason, $cash, $points, $isCashPositive, $isPointsPositive);
        }
    }
}
