# Cinch Schema

The cinch schema is a set of tables that track deployed (committed or reverted) changes to the target database.
The schema is always named `cinch_$PROJECT`. Although there are some differences in definition between
mysql and postgres, they behave identically and have the same table layouts.

> cinch always references its internal schema objects using schema qualified names: cinch_myproject.change.
> The cinch schema doesn't need to be in the postgres search_path.

# Database Support

Cinch supports MySQL 5.7/8 and PostgreSQL 12/13/14.

# Unicode Requirements

For PostgreSQL, cinch expects the database locale codeset
(language_territory.codeset like `en_US.UTF-8`, `fr_BE.UTF-8`, etc.) to be using UTF-8. language_territory can be set
to anything. More information can be found here: [PostgreSQL Locale Support](https://www.postgresql.org/docs/current/locale.html).
Cinch also makes use of the ICU provider to create a case-insensitive `COLLATION`.

> This is not required for MySQL, as character sets can be set on a per table and column level.

# Deployment Table

Whenever a change is committed or reverted, it gets a record within this table. These records are never deleted
or updated and can be treated as a log. There is one exception: when tagging the database. In this case,
the latest deployment record's `tag` is updated.

## Columns

* `deployment_id bigint` primary key for a deployment
* `deployer varchar(64)` who initiated the deployment
* `tag varchar(64)` tag name, can be `null`
* `command varchar(16)` command executed: `'commit'` or `'revert'`
* `environment json | jsonb` configuration, options, etc. of client application that executed the deployment
* `error_code int` zero for success and non-zero when an error occurred
* `error_message varchar(255)` non-empty error message when `error_code` is non-zero and `null` otherwise
* `error_trace json | jsonb` optional application stack trace, that is always `null` when `error_code` is zero
* `app_name varchar(64)` name of client application that executed the deployment
* `app_version varchar(16)` version of the client application that executed the deployment, using [semantic versioning 2.0.0](https://semver.org/)
* `started_at datetime(6) | timestamptz(6)` UTC time when deployment started
* `ended_at datetime(6) | timestamptz(6)` UTC time when deployment ended

# change table

This table represents the changes that were committed or reverted during a deployment. Just like the deployment table, 
records are not updated or deleted. If a revert occurs, a new record is added is to the table in `REVERTED` status. 
The original `COMMITTED` record remains. This is a log.

## change columns

The primary key of this table is `(location, deployment_id)`.

### location varchar(760)

The accent and case-insensitive location of this change script. For the cinch CLI, this is the script path
relative to the project. For other client applications, this could be an HTTP url, database access information,
etc. The only requirement is that this value uniquely identify the script.
> This is a very wide field, allowing for 760 characters (not bytes). Due to database key length
> constraints, this is the widest it can be.

### deployment_id bigint

The deployment this change belongs to -- foreign key.

### deployed_at datetime(6) or timestamptz(6)

The time this change was successfully deployed. This is not insertion time, which cinch does not track. This does,
however, define the order in which changes were executed. On revert, this column is used to revert in descending
order.

### checksum varchar(64)

The sha256 (lowercase hex) of the change script at the time it was deployed. The checksum is of the entire script
file, whether that be SQL or PHP. 
> If this value changes after being deployed, cinch will raise an error. The only exception are scripts
> marked as run-always or run-on-change.

### status varchar(16)

The status of the change. Valid status are `'COMMITTED', 'RECOMMITTED', 'REVERTED', 'SKIPPED'`. `RECOMMITTED`
only applies to change scripts marked as run-always or run-on-change. `SKIPPED` applies to change scripts marked
as run-always, but had no changes, or run-never.

### author varchar(64)

The name of the change script author.

### authored_at datetime(6) or timestamptz(6)

The time the change script was authored.

### language char(3)

The language of the change script. This can be `'PHP' or 'SQL'`.

### description varchar(255)

A description of the change. This must be provided: cannot be null or an empty string. You will thank cinch
months later, when revisiting changes to see what happened.

### revertable bool

This indicates if the change script is revertable. For SQL scripts, this mean it has a `-- @revert` section.
For PHP scripts, this means it implements `Cinch\Domain\Project\Revertable`. 
> a change could block a revert deployment if it is not revertable. In most cases, a revert-ability  
> should be present. This value is either `t` for true or `f` for false.