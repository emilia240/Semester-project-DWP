<?php
/*
ReportModel contains the database queries for reports.
This step reports comments; the database also allows a report about a post or user.
*/
class ReportModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // idx_report_comment or idx_report_reporter helps find an earlier report.
    public function commentReportedBy(int $reporterId, int $commentId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `Report` WHERE `ReporterID` = :r AND `CommentID` = :c');
        $stmt->execute([':r' => $reporterId, ':c' => $commentId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // Save an open report. The database CHECK allows exactly one target.
    public function createForComment(int $reporterId, int $commentId, string $reason): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `Report` (`ReporterID`, `CommentID`, `Reason`, `Status`) VALUES (:r, :c, :reason, \'open\')'
        );
        $stmt->bindValue(':r', $reporterId, PDO::PARAM_INT);
        $stmt->bindValue(':c', $commentId, PDO::PARAM_INT);
        $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    // idx_report_reporter helps count reports for this member's hourly limit.
    public function countSince(int $reporterId, int $seconds): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `Report` WHERE `ReporterID` = :r AND `CreatedAt` > (NOW() - INTERVAL :s SECOND)'
        );
        $stmt->bindValue(':r', $reporterId, PDO::PARAM_INT);
        $stmt->bindValue(':s', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
