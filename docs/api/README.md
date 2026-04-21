# Merlin MVC API

## Classes & Interfaces overview

### `Merlin`

- [AppContext](AppContext.md) `Merlin\AppContext`
- [ResolvedRoute](ResolvedRoute.md) `Merlin\ResolvedRoute`
- [Crypt](Crypt.md) `Merlin\Crypt`
- [Exception](Exception.md) `Merlin\Exception`

### `Merlin\Cli`

- [Console](Cli_Console.md) `Merlin\Cli\Console`
- [Task](Cli_Task.md) `Merlin\Cli\Task`

### `Merlin\Cli\Tasks`

- [ModelSyncTask](Cli_Tasks_ModelSyncTask.md) `Merlin\Cli\Tasks\ModelSyncTask`

### `Merlin\Db`

- [Condition](Db_Condition.md) `Merlin\Db\Condition`
- [Database](Db_Database.md) `Merlin\Db\Database`
- [DatabaseManager](Db_DatabaseManager.md) `Merlin\Db\DatabaseManager`
- [Exception](Db_Exception.md) `Merlin\Db\Exception`
- [Paginator](Db_Paginator.md) `Merlin\Db\Paginator`
- [Query](Db_Query.md) `Merlin\Db\Query`
- [ResultSet](Db_ResultSet.md) `Merlin\Db\ResultSet`
- [Sql](Db_Sql.md) `Merlin\Db\Sql`
- [SqlCase](Db_SqlCase.md) `Merlin\Db\SqlCase`

### `Merlin\Db\Exceptions`

- [TransactionLostException](Db_Exceptions_TransactionLostException.md) `Merlin\Db\Exceptions\TransactionLostException`

### `Merlin\Http`

- [Cookie](Http_Cookie.md) `Merlin\Http\Cookie`
- [Cookies](Http_Cookies.md) `Merlin\Http\Cookies`
- [Request](Http_Request.md) `Merlin\Http\Request`
- [Response](Http_Response.md) `Merlin\Http\Response`
- [Session](Http_Session.md) `Merlin\Http\Session`
- [SessionMiddleware](Http_SessionMiddleware.md) `Merlin\Http\SessionMiddleware`
- [UploadedFile](Http_UploadedFile.md) `Merlin\Http\UploadedFile`

### `Merlin\Mvc`

- [Controller](Mvc_Controller.md) `Merlin\Mvc\Controller`
- [Dispatcher](Mvc_Dispatcher.md) `Merlin\Mvc\Dispatcher`
- [Exception](Mvc_Exception.md) `Merlin\Mvc\Exception`
- [MiddlewareInterface](Mvc_MiddlewareInterface.md) `Merlin\Mvc\MiddlewareInterface`
- [Model](Mvc_Model.md) `Merlin\Mvc\Model`
- [ModelMapping](Mvc_ModelMapping.md) `Merlin\Mvc\ModelMapping`
- [Router](Mvc_Router.md) `Merlin\Mvc\Router`
- [ViewEngine](Mvc_ViewEngine.md) `Merlin\Mvc\ViewEngine`

### `Merlin\Mvc\Engines\Adapters`

- [BladeAdapter](Mvc_Engines_Adapters_BladeAdapter.md) `Merlin\Mvc\Engines\Adapters\BladeAdapter`
- [PlatesAdapter](Mvc_Engines_Adapters_PlatesAdapter.md) `Merlin\Mvc\Engines\Adapters\PlatesAdapter`
- [TwigAdapter](Mvc_Engines_Adapters_TwigAdapter.md) `Merlin\Mvc\Engines\Adapters\TwigAdapter`

### `Merlin\Mvc\Engines`

- [ClarityEngine](Mvc_Engines_ClarityEngine.md) `Merlin\Mvc\Engines\ClarityEngine`
- [NativeEngine](Mvc_Engines_NativeEngine.md) `Merlin\Mvc\Engines\NativeEngine`

### `Merlin\Mvc\Exceptions`

- [ActionNotFoundException](Mvc_Exceptions_ActionNotFoundException.md) `Merlin\Mvc\Exceptions\ActionNotFoundException`
- [ControllerNotFoundException](Mvc_Exceptions_ControllerNotFoundException.md) `Merlin\Mvc\Exceptions\ControllerNotFoundException`
- [InvalidControllerException](Mvc_Exceptions_InvalidControllerException.md) `Merlin\Mvc\Exceptions\InvalidControllerException`

### `Merlin\Sync`

- [CodeGenerator](Sync_CodeGenerator.md) `Merlin\Sync\CodeGenerator`
- [ModelDiff](Sync_ModelDiff.md) `Merlin\Sync\ModelDiff`
- [DiffOperation](Sync_DiffOperation.md) `Merlin\Sync\DiffOperation`
- [AddProperty](Sync_AddProperty.md) `Merlin\Sync\AddProperty`
- [RemoveProperty](Sync_RemoveProperty.md) `Merlin\Sync\RemoveProperty`
- [AddAccessor](Sync_AddAccessor.md) `Merlin\Sync\AddAccessor`
- [UpdatePropertyType](Sync_UpdatePropertyType.md) `Merlin\Sync\UpdatePropertyType`
- [UpdatePropertyComment](Sync_UpdatePropertyComment.md) `Merlin\Sync\UpdatePropertyComment`
- [UpdateClassComment](Sync_UpdateClassComment.md) `Merlin\Sync\UpdateClassComment`
- [ModelParser](Sync_ModelParser.md) `Merlin\Sync\ModelParser`
- [ParsedModel](Sync_ParsedModel.md) `Merlin\Sync\ParsedModel`
- [ParsedProperty](Sync_ParsedProperty.md) `Merlin\Sync\ParsedProperty`
- [SyncOptions](Sync_SyncOptions.md) `Merlin\Sync\SyncOptions`
- [SyncResult](Sync_SyncResult.md) `Merlin\Sync\SyncResult`
- [SyncRunner](Sync_SyncRunner.md) `Merlin\Sync\SyncRunner`

### `Merlin\Sync\Schema`

- [MySqlSchemaProvider](Sync_Schema_MySqlSchemaProvider.md) `Merlin\Sync\Schema\MySqlSchemaProvider`
- [PostgresSchemaProvider](Sync_Schema_PostgresSchemaProvider.md) `Merlin\Sync\Schema\PostgresSchemaProvider`
- [SchemaProvider](Sync_Schema_SchemaProvider.md) `Merlin\Sync\Schema\SchemaProvider`
- [TableSchema](Sync_Schema_TableSchema.md) `Merlin\Sync\Schema\TableSchema`
- [ColumnSchema](Sync_Schema_ColumnSchema.md) `Merlin\Sync\Schema\ColumnSchema`
- [IndexSchema](Sync_Schema_IndexSchema.md) `Merlin\Sync\Schema\IndexSchema`
- [SqliteSchemaProvider](Sync_Schema_SqliteSchemaProvider.md) `Merlin\Sync\Schema\SqliteSchemaProvider`

### `Merlin\Validation`

- [FieldValidator](Validation_FieldValidator.md) `Merlin\Validation\FieldValidator`
- [ValidationException](Validation_ValidationException.md) `Merlin\Validation\ValidationException`
- [Validator](Validation_Validator.md) `Merlin\Validation\Validator`

