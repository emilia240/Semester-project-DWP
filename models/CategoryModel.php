<?php
/*
CategoryModel reads the crafts and saves a member's choices in UserCategory.
The junction table connects each user to the categories they picked.
*/
class CategoryModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Show the available crafts in alphabetical order.
    public function all(): array
    {
        $stmt = $this->db->prepare('SELECT CategoryID, CategoryName FROM `Category` ORDER BY CategoryName');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Return the chosen category ids as integers, for ticking the saved boxes.
    public function idsForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT CategoryID FROM `UserCategory` WHERE UserID = :u');
        $stmt->execute([':u' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // No chosen crafts means join step 2 is not finished.
    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `UserCategory` WHERE UserID = :u');
        $stmt->execute([':u' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    // A transaction keeps the old picks if saving the new ones fails.
    public function replaceForUser(int $userId, array $categoryIds): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM `UserCategory` WHERE UserID = :u');
            $del->execute([':u' => $userId]);

            $ins = $this->db->prepare('INSERT INTO `UserCategory` (UserID, CategoryID) VALUES (:u, :c)');
            foreach ($categoryIds as $categoryId) {
                $ins->execute([':u' => $userId, ':c' => $categoryId]);
            }
            $this->db->commit();
        } catch (PDOException $ex) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $ex;
        }
    }
}
