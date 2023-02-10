
create schema ${schema};

-- case-insensitive and accent-insensitive collation
create collation ${utf8ci} (provider = 'icu', locale = '@colStrength=primary', deterministic = false);

create table ${cinch}
(
    schema_version varchar(32) collate ${ascii} primary key not null,
    schema_creator smallint not null,  -- forge a bool of 0|1, check constraint used
    description varchar(255) collate ${utf8ci} not null,
    release_date date not null,
    created_at timestamptz(6) not null unique,
    constraint "invalid semantic version for cinch schema" check (schema_version ~ '${semver_pattern}'),
    constraint "schema_creator must be 0 or 1" check (schema_creator in (0, 1)),
    constraint "cinch description cannot be empty" check (description <> '')
);

insert into ${cinch} values (${schema_version}, ${schema_creator}, ${schema_description}, ${release_date}, ${created_at});

create table ${deployment}
(
    tag varchar(64) collate ${utf8ci} primary key not null,
    deployer varchar(64) collate ${utf8ci} not null,
    command varchar(16) collate ${ascii} not null,
    application varchar(128) collate ${utf8ci} not null,
    schema_version varchar(32) collate ${ascii} not null references ${cinch} (schema_version) on delete restrict,
    error varchar(512) collate ${utf8ci} null default null,
    error_details jsonb null default null,
    started_at timestamptz(6) not null,
    ended_at timestamptz(6) null default null,
    constraint "tag cannot be empty" check (tag <> ''),
    constraint "unknown command" check (command in (${commands})),
    constraint "deployer cannot be empty" check (deployer <> ''),
    constraint "application cannot be empty" check (application <> ''),
    constraint "error must be null or not empty" check (error is null or error <> ''),
    constraint "error_details must be null when error is null" check (error is not null or error_details is null)
);

create index deployment_deployer_idx on ${deployment} (deployer);
create index deployment_command_idx on ${deployment} (command);
create index deployment_error_idx on ${deployment} (error);
create index deployment_schema_version_idx on ${deployment} (schema_version);

create table ${change}
(
    path varchar(512) collate ${utf8ci} not null,
    tag varchar(64) collate ${utf8ci} not null references ${deployment} (tag) on delete restrict,
    migrate_policy varchar(16) collate ${ascii} not null,
    status varchar(16) collate ${ascii} not null,
    author varchar(64) collate ${utf8ci} not null,
    checksum varchar(64) collate ${ascii} not null,
    description varchar(255) collate ${utf8ci} not null,
    labels varchar(255) collate ${utf8ci} not null,
    authored_at timestamptz(6) not null,
    deployed_at timestamptz(6) not null,
    primary key (path, tag),
    constraint "path cannot be empty" check (path <> ''),
    constraint "checksum must be between 32 and 64 lowercase hex" check (checksum ~ '^[a-f0-9]{32,64}$'),
    constraint "unknown status" check (status in (${statuses})),
    constraint "author cannot be empty" check (author <> ''),
    constraint "change description cannot be empty" check (description <> ''),
    constraint "unknown migrate policy" check (migrate_policy in (${migrate_policies}))
);

create index change_tag_idx on ${change} (tag);
create index change_labels_idx on ${change} (labels);
create index change_status_idx on ${change} (status);
create unique index change_deployed_at_idx on ${change} (deployed_at desc);
