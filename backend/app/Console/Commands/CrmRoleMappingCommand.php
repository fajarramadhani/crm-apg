<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CrmRoleMappingCommand extends Command
{
    private const COLUMNS = [
        'user_id',
        'name',
        'email',
        'current_role',
        'proposed_role',
        'reason',
        'approved_by',
        'mapping_status',
        'notes',
    ];

    private const FINAL_ROLES = [
        'requester',
        'supervisor_it',
        'pic_it_support',
        'pic_it_develop',
        'admin',
    ];

    protected $signature = 'crm:role-mapping
        {--file= : Path to the role mapping CSV}
        {--dry-run : Validate and report proposed changes without writing}';

    protected $description = 'Validate a CRM role mapping CSV without changing user roles';

    public function handle(): int
    {
        if (! $this->option('dry-run')) {
            $this->error('The --dry-run option is required. This command has no apply mode.');

            return self::INVALID;
        }

        $path = trim((string) $this->option('file'));
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            $this->error('The --file option must reference a readable CSV file.');

            return self::INVALID;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('The CSV file could not be opened.');

            return self::INVALID;
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if (isset($header[0])) {
                $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            }

            if ($header !== self::COLUMNS) {
                $this->error('Invalid CSV header. Expected: '.implode(',', self::COLUMNS));

                return self::INVALID;
            }

            $errors = [];
            $proposals = [];
            $seenUserIds = [];
            $line = 1;

            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;

                if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
                    continue;
                }

                if (count($values) !== count(self::COLUMNS)) {
                    $errors[] = "Row {$line}: expected ".count(self::COLUMNS).' columns, found '.count($values).'.';

                    continue;
                }

                $row = array_combine(self::COLUMNS, array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $values,
                ));

                $requiredColumns = ['user_id', 'name', 'email', 'current_role', 'proposed_role', 'reason', 'mapping_status'];
                $missing = array_values(array_filter(
                    $requiredColumns,
                    static fn (string $column): bool => $row[$column] === '',
                ));
                if ($missing !== []) {
                    $errors[] = "Row {$line}: empty required values: ".implode(', ', $missing).'.';

                    continue;
                }

                if (filter_var($row['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    $errors[] = "Row {$line}: user_id must be a positive integer.";

                    continue;
                }

                $userId = (int) $row['user_id'];
                if (isset($seenUserIds[$userId])) {
                    $errors[] = "Row {$line}: user_id {$userId} is duplicated (first seen on row {$seenUserIds[$userId]}).";

                    continue;
                }
                $seenUserIds[$userId] = $line;

                if (! in_array($row['proposed_role'], self::FINAL_ROLES, true)) {
                    $errors[] = "Row {$line}: proposed_role must be one of ".implode(', ', self::FINAL_ROLES).'.';

                    continue;
                }

                $user = User::query()->with('role')->find($userId);
                if (! $user) {
                    $errors[] = "Row {$line}: user_id {$userId} does not exist.";

                    continue;
                }

                $identityErrors = [];
                if ($user->name !== $row['name']) {
                    $identityErrors[] = 'name does not match';
                }
                if ($user->email !== $row['email']) {
                    $identityErrors[] = 'email does not match';
                }
                if ($user->role?->key !== $row['current_role']) {
                    $identityErrors[] = 'current_role does not match the current role';
                }

                if ($identityErrors !== []) {
                    $errors[] = "Row {$line}: ".implode('; ', $identityErrors).'.';

                    continue;
                }

                $proposals[] = [
                    $user->id,
                    $user->name,
                    $user->email,
                    $row['current_role'],
                    $row['proposed_role'],
                    $row['current_role'] === $row['proposed_role'] ? 'NO CHANGE' : 'CHANGE',
                    $row['reason'],
                    $row['approved_by'],
                    $row['mapping_status'],
                    $row['notes'],
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('Dry-run failed validation. No database changes were made.');

            return self::INVALID;
        }

        if ($proposals !== []) {
            $this->table(
                ['User ID', 'Name', 'Email', 'Current role', 'Proposed role', 'Result', 'Reason', 'Approved by', 'Status', 'Notes'],
                $proposals,
            );
        }

        $changes = count(array_filter($proposals, static fn (array $proposal): bool => $proposal[5] === 'CHANGE'));
        $this->line("Proposed changes: {$changes}; unchanged rows: ".(count($proposals) - $changes).'.');
        $this->info('Dry-run passed: '.count($proposals).' row(s) validated. No database changes were made.');

        return self::SUCCESS;
    }
}
