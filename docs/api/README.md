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

### `Merlin\Core`

- [Controller](Core_Controller.md) `Merlin\Core\Controller`
- [Dispatcher](Core_Dispatcher.md) `Merlin\Core\Dispatcher`
- [Exception](Core_Exception.md) `Merlin\Core\Exception`
- [MiddlewareInterface](Core_MiddlewareInterface.md) `Merlin\Core\MiddlewareInterface`
- [Model](Core_Model.md) `Merlin\Core\Model`
- [ModelMapping](Core_ModelMapping.md) `Merlin\Core\ModelMapping`
- [Router](Core_Router.md) `Merlin\Core\Router`
- [ViewEngine](Core_ViewEngine.md) `Merlin\Core\ViewEngine`

### `Merlin\Core\Engines\Adapters`

- [BladeAdapter](Core_Engines_Adapters_BladeAdapter.md) `Merlin\Core\Engines\Adapters\BladeAdapter`
- [PlatesAdapter](Core_Engines_Adapters_PlatesAdapter.md) `Merlin\Core\Engines\Adapters\PlatesAdapter`
- [TwigAdapter](Core_Engines_Adapters_TwigAdapter.md) `Merlin\Core\Engines\Adapters\TwigAdapter`

### `Merlin\Core\Engines`

- [ClarityEngine](Core_Engines_ClarityEngine.md) `Merlin\Core\Engines\ClarityEngine`
- [NativeEngine](Core_Engines_NativeEngine.md) `Merlin\Core\Engines\NativeEngine`

### `Merlin\Core\Exceptions`

- [ActionNotFoundException](Core_Exceptions_ActionNotFoundException.md) `Merlin\Core\Exceptions\ActionNotFoundException`
- [ControllerNotFoundException](Core_Exceptions_ControllerNotFoundException.md) `Merlin\Core\Exceptions\ControllerNotFoundException`
- [InvalidControllerException](Core_Exceptions_InvalidControllerException.md) `Merlin\Core\Exceptions\InvalidControllerException`

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

