<?= "<?php\n" ?>

namespace <?= $namespace; ?>;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\MappedSuperclass\AbstractLogEntry;
use Gedmo\Loggable\Entity\Repository\LogEntryRepository;

#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
#[ORM\Table(name: 'idm_user_premium_log', options: ['row_format' => 'DYNAMIC'])]
#[ORM\Index(name: 'idm_user_premium_log_class_lookup_idx', columns: ['object_class'])]
#[ORM\Index(name: 'idm_user_premium_log_date_lookup_idx', columns: ['logged_at'])]
#[ORM\Index(name: 'idm_user_premium_log_user_lookup_idx', columns: ['username'])]
#[ORM\Index(name: 'idm_user_premium_log_version_lookup_idx', columns: ['object_id', 'object_class', 'version'])]
class PremiumLog extends AbstractLogEntry
{
/* All required columns are mapped through inherited superclass */
}
