<?php
/*
SiteSettingModel reads the site's settings.
Join step 3 uses its community rules from the single SiteSetting row.
*/
class SiteSettingModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // A missing row is an error, not empty rules that somebody can agree to.
    public function rules(): ?string
    {
        $stmt = $this->db->prepare('SELECT RulesAndRegulations FROM `SiteSetting` WHERE SiteSettingID = 1');
        $stmt->execute();
        $text = $stmt->fetchColumn();
        if ($text === false || trim((string)$text) === '') {
            error_log('join guidelines: site rules are missing or empty');
            return null;
        }
        return (string)$text;
    }
}
