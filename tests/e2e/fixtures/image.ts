/**
 * Minimal 1×1 transparent PNG encoded as a Buffer.
 * Used for upload tests that need a real image without external files.
 */
export const TRANSPARENT_PNG_1X1 = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64',
);

/**
 * Minimal valid JPEG 1×1 white pixel.
 */
export const WHITE_JPEG_1X1 = Buffer.from(
  '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAABgUEB/8QAIxAAAQMEAgMBAAAAAAAAAAAAAQIDBBEhBRIxQWH/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AoNc3ue4bVtuOW+vkdaVSSyiKSEuFCFAgeUHBBwQfI4rNaaikoqiGppJmTQTMEkcjDlrmkZBB9EFB/9k=',
  'base64',
);

/**
 * Creates a fake "binary" buffer to simulate an invalid file type (.exe).
 * Just random bytes with a PE header signature.
 */
export const FAKE_EXE_BUFFER = Buffer.from('4D5A9000', 'hex');
