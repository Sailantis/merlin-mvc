# 🧩 Class: UploadedFile

**Full name:** [Azera\Http\UploadedFile](../../src/Http/UploadedFile.php)

Represents a single file uploaded with an HTTP multipart request.

Created from the $_FILES superglobal by [`Request::getFile()`](Http_Request.md#getfile) /
[`Request::getFiles()`](Http_Request.md#getfiles). Call `isValid()` before processing
and `moveTo()` to persist the file.

## 🚀 Public methods

### __construct() · [source](../../src/Http/UploadedFile.php#L23)

`public function __construct(string $name, string $type, string $tmpName, int $error, int $size): mixed`

Create a new UploadedFile from raw PHP file upload data.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Original client-supplied file name. |
| `$type` | string | - | Client-supplied MIME type (not verified). |
| `$tmpName` | string | - | Temporary path on the server. |
| `$error` | int | - | One of the UPLOAD_ERR_* constants. |
| `$size` | int | - | File size in bytes. |

**➡️ Return value**

- Type: mixed


---

### getClientFilename() · [source](../../src/Http/UploadedFile.php#L39)

`public function getClientFilename(): string`

Return the original file name as provided by the client.

Do NOT use this value for file system operations without sanitising it first.

**➡️ Return value**

- Type: string
- Description: Client-supplied file name.


---

### getClientMediaType() · [source](../../src/Http/UploadedFile.php#L49)

`public function getClientMediaType(): string`

Return the MIME type as provided by the client (not verified server-side).

**➡️ Return value**

- Type: string
- Description: Client-supplied media type (e.g. "image/jpeg").


---

### getSize() · [source](../../src/Http/UploadedFile.php#L59)

`public function getSize(): int`

Return the file size in bytes as reported by the upload.

**➡️ Return value**

- Type: int
- Description: File size in bytes.


---

### isValid() · [source](../../src/Http/UploadedFile.php#L69)

`public function isValid(): bool`

Check whether the file was uploaded without errors.

**➡️ Return value**

- Type: bool
- Description: True if the upload succeeded (UPLOAD_ERR_OK).


---

### moveTo() · [source](../../src/Http/UploadedFile.php#L80)

`public function moveTo(string $targetPath): void`

Move the uploaded file to a permanent location.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$targetPath` | string | - | Destination file path. |

**➡️ Return value**

- Type: void

**⚠️ Throws**

- RuntimeException  If the upload is invalid or the move fails.



---

[Back to the Index ⤴](README.md)
