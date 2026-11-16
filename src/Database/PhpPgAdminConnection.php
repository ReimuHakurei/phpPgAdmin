<?php

declare(strict_types=1);

namespace PhpPgAdmin\Database;

use PhpPgAdmin\Application\DTO\{
    Language as DTOLanguage,
    Role as DTORole,
    ServerSession as DTOServerSession,
    Tablespace as DTOTablespace,
};
use PhpPgAdmin\Config;
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\DDD\ValueObjects\{Language, Role, Tablespace};
use PhpPgAdmin\DDD\ValueObjects\Server\SslMode;
use PhpPgAdmin\Enums\RevokeType;

final class PhpPgAdminConnection extends \PDO
{
    /**
     * Map of database encoding names to HTTP encoding names. If a
     * database encoding does not appear in this list, then its HTTP
     * encoding name is the same as its database encoding name.
     */
    public const CODEMAP = [
        'BIG5' => 'BIG5',
        'EUC_CN' => 'GB2312',
        'EUC_JP' => 'EUC-JP',
        'EUC_KR' => 'EUC-KR',
        'EUC_TW' => 'EUC-TW',
        'GB18030' => 'GB18030',
        'GBK' => 'GB2312',
        'ISO_8859_5' => 'ISO-8859-5',
        'ISO_8859_6' => 'ISO-8859-6',
        'ISO_8859_7' => 'ISO-8859-7',
        'ISO_8859_8' => 'ISO-8859-8',
        'JOHAB' => 'CP1361',
        'KOI8' => 'KOI8-R',
        'LATIN1' => 'ISO-8859-1',
        'LATIN2' => 'ISO-8859-2',
        'LATIN3' => 'ISO-8859-3',
        'LATIN4' => 'ISO-8859-4',
        'LATIN5' => 'ISO-8859-9',
        'LATIN6' => 'ISO-8859-10',
        'LATIN7' => 'ISO-8859-13',
        'LATIN8' => 'ISO-8859-14',
        'LATIN9' => 'ISO-8859-15',
        'LATIN10' => 'ISO-8859-16',
        'SJIS' => 'SHIFT_JIS',
        'SQL_ASCII' => 'US-ASCII',
        'UHC' => 'WIN949',
        'UTF8' => 'UTF-8',
        'WIN866' => 'CP866',
        'WIN874' => 'CP874',
        'WIN1250' => 'CP1250',
        'WIN1251' => 'CP1251',
        'WIN1252' => 'CP1252',
        'WIN1256' => 'CP1256',
        'WIN1258' => 'CP1258',
    ];
    public const MAX_NAME_LENGTH = 63;

    public static function create(
        string $host,
        int $port,
        string $sslmode,
        string $user,
        string $password,
        string $database,
    ): self {
        $dsn = "pgsql:host={$host};port={$port};sslmode={$sslmode};dbname={$database}";
        $pdo = new self(dsn: $dsn, username: $user, password: $password);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    /**
     * @param string $database Pages about objects inside a database (languages, schemas, …) need a connection to that
     *                         database, the server-wide objects can be read from any database.
     */
    public static function createFromServerSession(?ServerSession $serverSession, string $database = 'postgres'): ?self
    {
        if ($serverSession === null) {
            return null;
        }

        $connection = self::create(
            host: (string)$serverSession->Host,
            port: $serverSession->Port->Value,
            sslmode: $serverSession->SslMode->value,
            user: (string)$serverSession->Username,
            password: (string)$serverSession->Password,
            database: $database,
        );

        $connection->exec("SET client_encoding TO 'UTF-8'");
        $connection->exec("SET bytea_output TO escape");

        return $connection;
    }

    public function alterDatabase(
        string $dbName,
        string $newName,
        ?string $newOwner = null,
        ?string $comment = null,
    ): void {
        if (!$this->beginTransaction()) {
            throw new \PDOException('Failed to begin transaction.');
        }

        if ($dbName !== $newName) {
            try {
                $this->alterDatabaseRename($dbName, $newName);
            } catch (\PDOException $e) {
                if (!$this->rollBack()) {
                    throw new \PDOException('Failed to roll back transaction after renaming database.', 0, $e);
                }

                return;
            }

            $dbName = $newName;
        }

        if (!is_null($newOwner) && $newOwner !== '') {
            try {
                $this->alterDatabaseOwner($dbName, $newOwner);
            } catch (\PDOException $e) {
                if (!$this->rollBack()) {
                    throw new \PDOException('Failed to roll back transaction after altering database owner.', 0, $e);
                }

                return;
            }
        }

        try {
            $this->setDatabaseComment($dbName, $comment);
        } catch (\PDOException $e) {
            if (!$this->rollBack()) {
                throw new \PDOException('Failed to roll back transaction after setting database comment.', 0, $e);
            }

            return;
        }

        if (!$this->commit()) {
            throw new \PDOException('Failed to commit transaction.');
        }
    }

    public function alterDatabaseOwner(string $dbName, string $newOwner): void
    {
        $escapedDbName = self::escapeIdentifier($dbName);
        $escapedNewOwner = self::escapeIdentifier($newOwner);
        $statement = "ALTER DATABASE \"{$escapedDbName}\" OWNER TO \"{$escapedNewOwner}\"";

        if ($this->exec($statement) === false) {
            throw new \PDOException('Failed to execute SQL statement for altering database owner.');
        }
    }

    public function alterDatabaseRename(string $oldName, string $newName): void
    {
        $escapedOldName = self::escapeIdentifier($oldName);
        $escapedNewName = self::escapeIdentifier($newName);
        $statement = "ALTER DATABASE \"{$escapedOldName}\" RENAME TO \"{$escapedNewName}\"";

        if ($this->exec($statement) === false) {
            throw new \PDOException('Failed to execute SQL statement for renaming database.');
        }
    }

    public function createDatabase(
        string $database,
        string $encoding,
        string $tablespace = '',
        string $comment = '',
        string $template = 'template1',
        string $lcCollate = '',
        string $lcCType = '',
    ): void {
        $escapedDatabase = self::escapeIdentifier($database);
        $escapedTemplate = self::escapeIdentifier($template);
        $sql = "CREATE DATABASE \"{$escapedDatabase}\" WITH TEMPLATE = \"{$escapedTemplate}\"";
        $sqlParams = [];

        if ($encoding !== '') {
            if (!in_array(needle: $encoding, haystack: array_keys(self::CODEMAP), strict: true)) {
                throw new \InvalidArgumentException("Invalid encoding: {$encoding}");
            }

            $sql .= " ENCODING = '{$encoding}'";
        }

        $availableCollations = $this->getAvailableCollations();

        if ($lcCollate !== '') {
            if (!in_array(needle: $lcCollate, haystack: $availableCollations, strict: true)) {
                throw new \InvalidArgumentException("Invalid LC_COLLATE: {$lcCollate}");
            }

            $sql .= " LC_COLLATE = '{$lcCollate}'";
        }

        if ($lcCType !== '') {
            if (!in_array(needle: $lcCType, haystack: $availableCollations, strict: true)) {
                throw new \InvalidArgumentException("Invalid LC_CTYPE: {$lcCType}");
            }

            $sql .= " LC_CTYPE = '{$lcCType}'";
        }

        if ($tablespace !== '') {
            $escapedTablespace = self::escapeIdentifier($tablespace);
            $sql .= " TABLESPACE \"{$escapedTablespace}\"";
        }

        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for creating database.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for creating database.');
        }

        if ($comment !== '') {
            $this->setDatabaseComment($database, $comment);
        }
    }

    /**
     * @param ?array<string> $memberOf Roles to which the new role will be immediately added as a new member
     * @param ?array<string> $members Roles which are automatically added as members of the new role
     * @param ?array<string> $adminMembers Roles which are automatically added as admin members of the new role
     */
    public function createRole(
        Role $role,
        string $password,
        ?array $memberOf = null,
        ?array $members = null,
        ?array $adminMembers = null,
    ): void {
        $escapedRolename = self::escapeIdentifier($role->Name);
        $sql = "CREATE ROLE \"{$escapedRolename}\"";

        if ($password !== '') {
            $escapedEncryptedPassword = self::escapeIdentifier(self::encryptPassword($role->Name, $password));
            $sql .= " WITH ENCRYPTED PASSWORD '{$escapedEncryptedPassword}'";
        }

        $sql .= $role->IsSuperuser
            ? ' SUPERUSER'
            : ' NOSUPERUSER';
        $sql .= $role->CanCreateDb
            ? ' CREATEDB'
            : ' NOCREATEDB';
        $sql .= $role->CanCreateRole
            ? ' CREATEROLE'
            : ' NOCREATEROLE';
        $sql .= $role->CanInheritRights
            ? ' INHERIT'
            : ' NOINHERIT';
        $sql .= $role->CanLogin
            ? ' LOGIN'
            : ' NOLOGIN';

        $sql .= " CONNECTION LIMIT {$role->ConnectionLimit}";

        if (!is_null($role->Expires)) {
            $formattedExpiration = $role->Expires->format('Y-m-d\TH:i:s');
            $sql .= " VALID UNTIL '{$formattedExpiration}'";
        } else {
            $sql .= " VALID UNTIL 'infinity'";
        }

        if (is_array($memberOf) && !empty($memberOf)) {
            $sql .= ' IN ROLE "' . join('", "', $memberOf) . '"';
        }

        if (is_array($members) && !empty($members)) {
            $sql .= ' ROLE "' . join('", "', $members) . '"';
        }

        if (is_array($adminMembers) && !empty($adminMembers)) {
            $sql .= ' ADMIN "' . join('", "', $adminMembers) . '"';
        }

        if ($this->exec($sql) === false) {
            throw new \PDOException('Failed to execute SQL statement for creating role.');
        }
    }

    public function createTablespace(Tablespace $tablespace): void
    {
        $escapedName = self::escapeIdentifier((string)$tablespace->Name);
        $sql = "CREATE TABLESPACE \"{$escapedName}\"";

        if ((string)$tablespace->Owner !== '') {
            $escapedOwner = self::escapeIdentifier((string)$tablespace->Owner);
            $sql .= " OWNER \"{$escapedOwner}\"";
        }

        $escapedLocation = self::escapeIdentifier((string)$tablespace->Location);
        $sql .= " LOCATION '{$escapedLocation}'";

        if ($this->exec($sql) === false) {
            throw new \PDOException('Failed to execute SQL statement for creating tablespace.');
        }

        if ((string)$tablespace->Comment === '') {
            return;
        }

        $this->setCommentForTablespace((string)$tablespace->Name, (string)$tablespace->Comment);
    }

    public function dropDatabase(string $database): void
    {
        $escapedDatabase = self::escapeIdentifier($database);
        $statement = "DROP DATABASE \"{$escapedDatabase}\"";

        if ($this->exec($statement) === false) {
            throw new \PDOException('Failed to execute SQL statement for dropping database.');
        }
    }

    public function dropRole(string $rolename): void
    {
        $escapedRolename = self::escapeIdentifier($rolename);

        $statement = "DROP ROLE \"{$escapedRolename}\"";

        if ($this->exec($statement) === false) {
            throw new \PDOException('Failed to execute SQL statement for dropping role.');
        }
    }

    public function dropTablespace(string $tablespace): void
    {
        $escapedTablespace = self::escapeIdentifier($tablespace);

        $statement = "DROP TABLESPACE \"{$escapedTablespace}\"";

        if ($this->exec($statement) === false) {
            throw new \PDOException('Failed to execute SQL statement for dropping tablespace.');
        }
    }

    /**
     * @return array<string>
     */
    public function getAvailableCollations(): array
    {
        $query = "SELECT collname FROM pg_collation WHERE collname LIKE '%.%' ORDER BY collname";
        $statement = $this->prepare($query);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting available collations.');
        }

        if (!$statement->execute()) {
            throw new \PDOException('Failed to execute SQL statement for getting available collations.');
        }

        $collations = [];

        while ($row = $statement->fetch()) {
            if (is_array($row) && isset($row['collname']) && is_string($row['collname'])) {
                $collations[] = $row['collname'];
            }
        }

        return $collations;
    }

    public function getDatabaseComment(string $database): string
    {
        $sql = "SELECT description
            FROM pg_catalog.pg_database
                JOIN pg_catalog.pg_shdescription ON (oid=objoid AND classoid='pg_database'::regclass)
            WHERE pg_database.datname = :database";
        $sqlParams = ['database' => $database];
        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting database comment.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for getting database comment.');
        }

        $result = $statement->fetch();

        if ($result === false) { // no comment found
            return '';
        }

        if (!is_array($result)) {
            throw new \PDOException('Failed to fetch database comment.');
        }

        if (isset($result['description']) && is_string($result['description'])) {
            return $result['description'];
        }

        return '';
    }

    public function getDatabaseOwner(string $database): string
    {
        $sql = "SELECT usename
            FROM pg_user, pg_database
            WHERE pg_user.usesysid = pg_database.datdba AND pg_database.datname = :database";
        $sqlParams = [
            'database' => $database,
        ];
        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting database owner.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for getting database owner.');
        }

        $result = $statement->fetch();

        if (!is_array($result)) {
            throw new \PDOException('Failed to fetch database owner.');
        }

        if (isset($result['usename']) && is_string($result['usename'])) {
            return $result['usename'];
        }

        return '';
    }

    /**
     * @return array<array{
     *  'datname': string,
     *  'datowner': string,
     *  'datencoding': string,
     *  'datcollate': string,
     *  'datctype': string,
     *  'tablespace': string,
     *  'dbsize': int,
     *  'datcomment': string
     * }> The list of databases.
     */
    public function getDatabases(): array
    {
        $serverInfo = DTOServerSession::createFromRequestParameter();

        $whereClause = Config::showSystem()
            ? 'pdb.datallowconn'
            : 'NOT pdb.datistemplate';

        if (!is_null($serverInfo) && Config::ownedOnly() && !$this->isSuperUser()) {
            $whereClause = " AND pg_has_role('{$serverInfo->Username}'::name, pr.rolname, 'USAGE')";
        }

        $orderBy = "pdb.datname";

        $sql = "
            SELECT pdb.datname AS datname, pr.rolname AS datowner, pg_encoding_to_char(encoding) AS datencoding,
                pdb.datcollate, pdb.datctype,
                (SELECT description
                    FROM pg_catalog.pg_shdescription pd
                    WHERE pdb.oid=pd.objoid AND pd.classoid='pg_database'::regclass
                ) AS datcomment,
                (SELECT spcname
                    FROM pg_catalog.pg_tablespace pt
                    WHERE pt.oid=pdb.dattablespace
                ) AS tablespace,
                CASE WHEN pg_catalog.has_database_privilege(current_user, pdb.oid, 'CONNECT')
                    THEN pg_catalog.pg_database_size(pdb.oid)
                    ELSE -1 -- set this magic value, which we will convert to no access later
                END AS dbsize
            FROM pg_catalog.pg_database pdb
                LEFT JOIN pg_catalog.pg_roles pr ON (pdb.datdba = pr.oid)
            WHERE {$whereClause}
            ORDER BY {$orderBy}";

        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting databases.');
        }

        if (!$statement->execute()) {
            throw new \PDOException('Failed to execute SQL statement for getting databases.');
        }

        $result = [];
        $requiredFields = [
            'datname',
            'datowner',
            'datencoding',
            'datcollate',
            'datctype',
            'tablespace',
            'dbsize',
        ];

        while ($row = $statement->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($requiredFields as $field) {
                if (!isset($row[$field])) {
                    continue 2;
                }
            }

            if (
                !is_string($row['datname']) ||
                !is_string($row['datowner']) ||
                !is_string($row['datencoding']) ||
                !is_string($row['datcollate']) ||
                !is_string($row['datctype']) ||
                !is_string($row['tablespace']) ||
                !is_int($row['dbsize'])
            ) {
                continue;
            }

            $result[] = [
                'datcollate' => $row['datcollate'],
                'datcomment' => isset($row['datcomment']) && is_string($row['datcomment'])
                    ? $row['datcomment']
                    : '',
                'datctype' => $row['datctype'],
                'datencoding' => $row['datencoding'],
                'datname' => $row['datname'],
                'datowner' => $row['datowner'],
                'dbsize' => $row['dbsize'],
                'tablespace' => $row['tablespace'],
            ];
        }

        return $result;
    }

    /**
     * The languages of the connected database, sorted by name.
     *
     * @param bool $all Also the built-in languages (c, internal, sql), regardless of show_system.
     * @return array<Language>
     */
    public function getLanguages(bool $all = false): array
    {
        $sql = 'SELECT lanname, lanpltrusted, lanplcallfoid::pg_catalog.regproc AS lanplcallf
            FROM pg_catalog.pg_language';

        if (!Config::showSystem() && !$all) {
            $sql .= ' WHERE lanispl';
        }

        $sql .= ' ORDER BY lanname';

        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting languages.');
        }

        if (!$statement->execute()) {
            throw new \PDOException('Failed to execute SQL statement for getting languages.');
        }

        $languages = [];

        while ($row = $statement->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            $languages[] = DTOLanguage::createFromDbArray($row);
        }

        return $languages;
    }

    /**
     * Returns all role names which the role belongs to
     *
     * @return array<string>
     */
    public function getMemberOf(string $rolename): array
    {
        $sql = "SELECT rolname FROM pg_catalog.pg_roles R, pg_auth_members M
			WHERE R.oid = M.roleid
				AND member IN (
					SELECT oid FROM pg_catalog.pg_roles
					WHERE rolname = :rolename
                )
			ORDER BY rolname";
        $sqlParams = [
            'rolename' => $rolename,
        ];
        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting members of role.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for getting members of role.');
        }

        $rolenames = [];

        while ($row = $statement->fetch()) {
            if (is_array($row) && isset($row['rolname']) && is_string($row['rolname'])) {
                $rolenames[] = $row['rolname'];
            }
        }

        return $rolenames;
    }

    /**
     * Returns all role names that are members of a role
     *
     * @return array<string>
     */
    public function getMembers(string $rolename, bool $onlyAdminMembers = false): array
    {
        $adminOption = $onlyAdminMembers
            ? 't'
            : 'f';

        $sql = "SELECT rolname FROM pg_catalog.pg_roles R, pg_auth_members M
			WHERE R.oid = M.member AND admin_option = :admin_option
				AND roleid IN (
                    SELECT oid FROM pg_catalog.pg_roles
					WHERE rolname = :rolename
                )
			ORDER BY rolname";
        $sqlParams = [
            'admin_option' => $adminOption,
            'rolename' => $rolename,
        ];
        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting rolenames, that are members of role.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for getting rolenames, that are members of role.');
        }

        $rolenames = [];

        while ($row = $statement->fetch()) {
            if (is_array($row) && isset($row['rolname']) && is_string($row['rolname'])) {
                $rolenames[] = $row['rolname'];
            }
        }

        return $rolenames;
    }

    public function getRole(string $rolename): ?Role
    {
        $sql = "SELECT rolname, rolsuper, rolcreatedb, rolcreaterole, rolinherit,
            rolcanlogin, rolconnlimit, rolvaliduntil, rolconfig
			FROM pg_catalog.pg_roles WHERE rolname=:rolename";
        $sqlParams = [
            'rolename' => $rolename,
        ];

        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting role.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for getting role.');
        }

        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return DTORole::createFromDbArray($row);
    }

    /**
     * @return array<Role>
     */
    public function getRoles(string $excludeRolename = ''): array
    {
        $sql = 'SELECT rolname, rolsuper, rolcreatedb, rolcreaterole, rolinherit,
			rolcanlogin, rolconnlimit, rolvaliduntil, rolconfig
			FROM pg_catalog.pg_roles';
        $sqlParams = [];

        if ($excludeRolename) {
            $sql .= " WHERE rolname!=':excludeRolename'";
            $sqlParams['excludeRolename'] = $excludeRolename;
        }

        $sql .= ' ORDER BY rolname';
        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting roles.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for getting roles.');
        }

        $result = [];

        while ($row = $statement->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            $result[] = DTORole::createFromDbArray($row);
        }

        return $result;
    }

    public function getTablespace(string $tablespace): ?Tablespace
    {
        $sql = "SELECT spcname, pg_catalog.pg_get_userbyid(spcowner) AS spcowner,
                pg_catalog.pg_tablespace_location(oid) AS spclocation,
                (
                    SELECT description
                    FROM pg_catalog.pg_shdescription pd
                    WHERE pg_tablespace.oid=pd.objoid AND pd.classoid='pg_tablespace'::regclass
                ) AS spccomment
                FROM pg_catalog.pg_tablespace WHERE spcname=:tablespace";
        $sqlParams = [
            'tablespace' => $tablespace,
        ];

        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting tablespace.');
        }

        if (!$statement->execute($sqlParams)) {
            throw new \PDOException('Failed to execute SQL statement for getting tablespace.');
        }

        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return DTOTablespace::createFromDbArray($row);
    }

    /**
     * @return array<Tablespace>
     */
    public function getTablespaces(bool $all = false): array
    {
        $sql = "SELECT spcname,
            pg_catalog.pg_get_userbyid(spcowner) AS spcowner,
            pg_catalog.pg_tablespace_location(oid) AS spclocation,
            (
                SELECT description
                FROM pg_catalog.pg_shdescription pd
                WHERE pg_tablespace.oid=pd.objoid AND pd.classoid='pg_tablespace'::regclass
            ) AS spccomment
            FROM pg_catalog.pg_tablespace";

        if (!Config::showSystem() && !$all) {
            $sql .= ' WHERE spcname NOT LIKE $$pg\_%$$';
        }

        $sql .= " ORDER BY spcname";

        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting tablespaces.');
        }

        if (!$statement->execute()) {
            throw new \PDOException('Failed to execute SQL statement for getting tablespaces.');
        }

        $result = [];
        $requiredFields = [
            'spcname',
            'spcowner',
            'spclocation',
        ];

        while ($row = $statement->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($requiredFields as $field) {
                if (!isset($row[$field])) {
                    continue 2;
                }
            }

            if (!is_string($row['spcname']) || !is_string($row['spcowner']) || !is_string($row['spclocation'])) {
                continue;
            }

            $result[] = DTOTablespace::createFromDbArray($row);
        }

        return $result;
    }

    /**
     * @return array<array{
     *  'usename': string,
     *  'usesuper': bool,
     *  'usecreatedb': bool,
     *  'useexpires': string,
     *  'useconfig': string
     * }> The list of databases.
     */
    public function getUsers(): array
    {
        $sql = "SELECT usename, usesuper, usecreatedb, valuntil AS useexpires, useconfig
            FROM pg_user
            ORDER BY usename";
        $statement = $this->prepare($sql);

        if ($statement === false) {
            throw new \PDOException('Failed to prepare SQL statement for getting users.');
        }

        if (!$statement->execute()) {
            throw new \PDOException('Failed to execute SQL statement for getting users.');
        }

        $result = [];
        $requiredFields = [
            'usename',
            'usesuper',
            'usecreatedb',
        ];

        while ($row = $statement->fetch()) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($requiredFields as $field) {
                if (!isset($row[$field])) {
                    continue 2;
                }
            }

            if (!is_string($row['usename']) || !is_bool($row['usesuper']) || !is_bool($row['usecreatedb'])) {
                continue;
            }

            $result[] = [
                'useconfig' => isset($row['useconfig']) && is_string($row['useconfig'])
                    ? $row['useconfig']
                    : '',
                'usecreatedb' => $row['usecreatedb'],
                'useexpires' => isset($row['useexpires']) && is_string($row['useexpires'])
                    ? $row['useexpires']
                    : '',
                'usename' => $row['usename'],
                'usesuper' => $row['usesuper'],
            ];
        }

        return $result;
    }

    /**
     * Determines whether or not a user is a super user
     *
     * @param string $username The username of the user
     * @return bool true if is a super user, false otherwise.
     */
    public function isSuperUser(string $username = ''): bool
    {
        if (empty($username)) {
            $statement = $this->query("SHOW is_superuser");

            if ($statement !== false) {
                $isSuperUserColumn = $statement->fetchColumn();

                return $isSuperUserColumn === 'on';
            }
        }

        $sql = "SELECT usesuper FROM pg_user WHERE usename = :username";
        $statement = $this->prepare($sql);

        if ($statement === false) {
            return false;
        }

        if (!$statement->execute(['username' => $username])) {
            return false;
        }

        $rows = $statement->fetchAll();

        if (empty($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            if (is_array($row) && isset($row['usesuper']) && is_bool($row['usesuper'])) {
                return $row['usesuper'];
            }
        }

        return false;
    }

    public function setCommentForTablespace(string $tablespaceName, string $comment): void
    {
        $sql = "COMMENT ON TABLESPACE ";
        $sql .= "\"{$tablespaceName}\" IS ";

        if ($comment !== '') {
            $sql .= "'{$comment}';";
        } else {
            $sql .= 'NULL;';
        }

        if ($this->exec($sql) === false) {
            throw new \PDOException('Failed to execute SQL statement for set comment.');
        }
    }

    public function setDatabaseComment(string $database, ?string $comment = null): void
    {
        $escapedDatabase = self::escapeIdentifier($database);
        $statement = "COMMENT ON DATABASE \"{$escapedDatabase}\" IS ";
        $statement .= !is_null($comment)
            ? $this->quote($comment)
            : 'NULL';

        if ($this->exec($statement) === false) {
            throw new \PDOException('Failed to execute SQL statement for setting database comment.');
        }
    }

    /**
     * @param array<string> $newMemberOf Roles to which the new role will be immediately added as a new member
     * @param array<string> $newMembers Roles which are automatically added as members of the new role
     * @param array<string> $newAdminMembers Roles which are automatically added as admin members of the new role
     */
    public function updateRole(
        Role $role,
        ?string $password = null,
        array $newMemberOf = [],
        array $newMembers = [],
        array $newAdminMembers = [],
    ): void {
        $sql = "ALTER ROLE \"{$role->Name}\"";

        if (!is_null($password) && $password !== '') {
            $escapedEncryptedPassword = self::escapeIdentifier(self::encryptPassword($role->Name, $password));
            $sql .= " WITH ENCRYPTED PASSWORD '{$escapedEncryptedPassword}'";
        }

        $sql .= $role->IsSuperuser
            ? ' SUPERUSER'
            : ' NOSUPERUSER';
        $sql .= $role->CanCreateDb
            ? ' CREATEDB'
            : ' NOCREATEDB';
        $sql .= $role->CanCreateRole
            ? ' CREATEROLE'
            : ' NOCREATEROLE';
        $sql .= $role->CanInheritRights
            ? ' INHERIT'
            : ' NOINHERIT';
        $sql .= $role->CanLogin
            ? ' LOGIN'
            : ' NOLOGIN';

        $sql .= " CONNECTION LIMIT {$role->ConnectionLimit}";

        if (!is_null($role->Expires)) {
            $formattedExpiration = $role->Expires->format('Y-m-d\TH:i:s');
            $sql .= " VALID UNTIL '{$formattedExpiration}'";
        } else {
            $sql .= " VALID UNTIL 'infinity'";
        }

        if ($this->exec($sql) === false) {
            throw new \PDOException('Failed to execute SQL statement for updating role.');
        }

        $this->tryAlterMemberOf($newMemberOf, $role->Name);
        $this->tryAlterMembers($newMembers, $role->Name);
        $this->tryAlterAdminMembers($newAdminMembers, $role->Name);
    }

    public function updateTablespace(Tablespace $tablespace, string $oldTablespaceName): void
    {
        if (!$this->beginTransaction()) {
            throw new \PDOException('Failed to begin transaction.');
        }

        $escapedTablespaceName = self::escapeIdentifier((string)$oldTablespaceName);
        $escapedTablespaceOwner = self::escapeIdentifier((string)$tablespace->Owner);

        $statement = "ALTER TABLESPACE \"{$escapedTablespaceName}\" OWNER TO \"{$escapedTablespaceOwner}\"";

        if ($this->exec($statement) === false) {
            if (!$this->rollBack()) {
                throw new \PDOException('Failed to roll back transaction after altering tablespace owner.');
            }

            throw new \PDOException('Failed to execute SQL statement for altering tablespace owner.');
        }

        if ($oldTablespaceName !== (string)$tablespace->Name) {
            $statement = "ALTER TABLESPACE \"{$oldTablespaceName}\" RENAME TO \"{$tablespace->Name}\"";

            if ($this->exec($statement) === false) {
                if (!$this->rollBack()) {
                    throw new \PDOException('Failed to roll back transaction after renaming tablespace.');
                }

                throw new \PDOException('Failed to execute SQL statement for renaming tablespace.');
            }
        }

        $this->setCommentForTablespace((string)$tablespace->Name, (string)$tablespace->Comment);

        if (!$this->commit()) {
            throw new \PDOException('Failed to commit transaction.');
        }
    }

    public static function loginDataIsValid(
        string $host,
        int $port,
        SslMode $sslmode,
        string $user,
        string $password,
    ): bool {
        $dsn = "pgsql:host={$host};port={$port};sslmode={$sslmode->value}";

        try {
            new \PDO(dsn: $dsn, username: $user, password: $password);

            return true;
        } catch (\PDOException $e) {
            error_log($e->getMessage());

            return false;
        }
    }

    /**
     * Grants membership in a role
     *
     * @param $role The name of the target role
     * @param $rolename The name of the role that will belong to the target role
     */
    private function grantRole(string $role, string $rolename, bool $onlyAdminOption = false): void
    {
        $escapedRole = self::escapeIdentifier($role);
        $escapedRolename = self::escapeIdentifier($rolename);

        $sql = "GRANT \"{$escapedRole}\" TO \"{$escapedRolename}\"";

        if ($onlyAdminOption) {
            $sql .= ' WITH ADMIN OPTION';
        }

        if ($this->exec($sql) === false) {
            throw new \PDOException('Failed to execute SQL statement for grant role.');
        }
    }

    /**
     * Revokes membership in a role
     *
     * @param string $role The name of the target role
     * @param string $rolename The name of the role that will not belong to the target role
     */
    private function revokeRole(
        string $role,
        string $rolename,
        bool $onlyAdminOption = false,
        RevokeType $type = RevokeType::Restrict,
    ): void {
        $escapedRole = self::escapeIdentifier($role);
        $escapedRolename = self::escapeIdentifier($rolename);

        $sql = "REVOKE ";

        if ($onlyAdminOption) {
            $sql .= 'ADMIN OPTION FOR ';
        }

        $sql .= "\"{$escapedRole}\" FROM \"{$escapedRolename}\" {$type->value}";

        if ($this->exec($sql) === false) {
            throw new \PDOException('Failed to execute SQL statement for revoke role.');
        }
    }

    /**
     * @param array<string> $newMemberOf Roles to which the new role will be immediately added as a new member
     */
    private function tryAlterMemberOf(array $newMemberOf, string $roleName): void
    {
        $oldMemberOf = $this->getMemberOf($roleName);
        $memberOfToAdd = array_diff($newMemberOf, $oldMemberOf);
        $memberOfToRemove = array_diff($oldMemberOf, $newMemberOf);

        foreach ($memberOfToAdd as $addMember) {
            $this->grantRole($addMember, $roleName);
        }

        foreach ($memberOfToRemove as $removeMember) {
            $this->revokeRole($removeMember, $roleName, onlyAdminOption: false, type: RevokeType::Cascade);
        }
    }

    /**
     * @param array<string> $newMembers Roles which are automatically added as members of the new role
     */
    private function tryAlterMembers(array $newMembers, string $roleName): void
    {
        $oldMembers = $this->getMembers($roleName);
        $membersToAdd = array_diff($newMembers, $oldMembers);
        $membersToRemove = array_diff($oldMembers, $newMembers);

        foreach ($membersToAdd as $addMember) {
            $this->grantRole($roleName, $addMember);
        }

        foreach ($membersToRemove as $removeMember) {
            $this->revokeRole($roleName, $removeMember, onlyAdminOption: false, type: RevokeType::Cascade);
        }
    }

    /**
     * @param array<string> $newAdminMembers Roles which are automatically added as admin members of the new role
     */
    private function tryAlterAdminMembers(array $newAdminMembers, string $roleName): void
    {
        $oldAdminMembers = $this->getMembers($roleName, onlyAdminMembers: true);
        $adminMembersToAdd = array_diff($newAdminMembers, $oldAdminMembers);
        $adminMembersToRemove = array_diff($oldAdminMembers, $newAdminMembers);

        foreach ($adminMembersToAdd as $addMember) {
            $this->grantRole($roleName, $addMember, onlyAdminOption: true);
        }

        foreach ($adminMembersToRemove as $removeMember) {
            $this->revokeRole($roleName, $removeMember, onlyAdminOption: true, type: RevokeType::Cascade);
        }
    }

    /**
     * Helper function that computes encrypted PostgreSQL passwords
     */
    private static function encryptPassword(string $username, string $password): string
    {
        return 'md5' . md5($password . $username);
    }

    /**
     * From PHP 8.4 on, there is a native escapeIdentifier function.
     *
     * @see https://www.php.net/manual/en/pdo-pgsql.escapeidentifier.php
     */
    private static function escapeIdentifier(string $identifier): string
    {
        return str_replace('"', '""', $identifier);
    }
}
