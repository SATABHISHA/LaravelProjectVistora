# NewsFeed API Documentation

## Overview
This documentation covers all NewsFeed APIs including creating posts, comments, likes, and retrieving newsfeed data with complete examples and outputs.

## Database Tables

### 1. news_feed
Main table for storing news feed posts.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| puid | varchar(100) | Unique identifier (UUID) |
| corpId | varchar(10) | Corporate ID |
| EmpCode | varchar(20) | Employee code |
| companyName | varchar(100) | Company name |
| employeeFullName | varchar(150) | Full name of employee |
| body | text | Post content |
| date | varchar(20) | Date of post (YYYY-MM-DD format) |
| time | varchar(20) | Time of post |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### 2. news_feed_likes
Table for storing likes on news feed posts.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| corpId | varchar(10) | Corporate ID |
| puid | varchar(100) | Foreign key to news_feed.puid |
| EmpCode | varchar(20) | Employee code who liked |
| companyName | varchar(100) | Company name |
| employeeFullName | varchar(150) | Full name of employee |
| date | varchar(20) | Date of like |
| time | varchar(20) | Time of like |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### 3. news_feed_reviews
Table for storing comments on news feed posts. Allows multiple comments per user per post.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key (used for deleting specific comments) |
| corpId | varchar(10) | Corporate ID |
| puid | varchar(100) | Foreign key to news_feed.puid |
| EmpCode | varchar(20) | Employee code |
| companyName | varchar(100) | Company name |
| employeeFullName | varchar(150) | Full name of employee |
| isLiked | varchar(1) | Legacy field (not used for new comments) |
| comment | text | Comment text |
| date | varchar(20) | Date of comment |
| time | varchar(20) | Time of comment |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

---

## API Endpoints

## 1. Create News Feed Post

Creates a new news feed entry.

**Endpoint:** `POST /api/newsfeed`

**Request Body:**
```json
{
    "corpId": "CORP001",
    "EmpCode": "EMP001",
    "companyName": "Tech Solutions Ltd",
    "body": "Excited to announce our new product launch! 🚀",
    "date": "2026-01-31",
    "time": "14:30:00"
}
```

**Success Response (201 Created):**
```json
{
    "status": true,
    "message": "News feed created successfully",
    "data": {
        "id": 1,
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "corpId": "CORP001",
        "EmpCode": "EMP001",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "John Michael Doe",
        "body": "Excited to announce our new product launch! 🚀",
        "date": "2026-01-31",
        "time": "14:30:00",
        "created_at": "2026-01-31T14:30:00.000000Z",
        "updated_at": "2026-01-31T14:30:00.000000Z"
    }
}
```

**Error Responses:**

*Validation Error (422):*
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "body": ["The body field is required."],
        "corpId": ["The corp id field is required."]
    }
}
```

*Employee Not Found (404):*
```json
{
    "status": false,
    "message": "Employee not found with the provided EmpCode",
    "corpId": "CORP001",
    "EmpCode": "EMP999"
}
```

---

## 2. Get All News Feeds with Reviews and Likes

Retrieves all news feed posts with their reviews and likes count. Supports filtering by date range.

**Endpoint:** `GET /api/newsfeed-with-reviews`

**Query Parameters:**
- `corpId` (required): Corporate ID
- `companyName` (optional): Filter by company name
- `startDate` (optional): Filter posts from this date (format: YYYY-MM-DD)
- `endDate` (optional): Filter posts until this date (format: YYYY-MM-DD)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 10, max: 100)

**Example Requests:**

*Basic request:*
```
GET /api/newsfeed-with-reviews?corpId=CORP001&page=1&per_page=10
```

*With date filter:*
```
GET /api/newsfeed-with-reviews?corpId=CORP001&startDate=2026-01-01&endDate=2026-01-31&page=1&per_page=10
```

*With all filters:*
```
GET /api/newsfeed-with-reviews?corpId=CORP001&companyName=Tech%20Solutions%20Ltd&startDate=2026-01-15&endDate=2026-01-31&page=1&per_page=10
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "News feeds retrieved successfully",
    "filters": {
        "corpId": "CORP001",
        "companyName": "Tech Solutions Ltd",
        "startDate": "2026-01-15",
        "endDate": "2026-01-31"
    },
    "pagination": {
        "total": 25,
        "per_page": 10,
        "current_page": 1,
        "last_page": 3,
        "from": 1,
        "to": 10
    },
    "count": 10,
    "data": [
        {
            "id": 1,
            "corpId": "CORP001",
            "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
            "EmpCode": "EMP001",
            "companyName": "Tech Solutions Ltd",
            "employeeFullName": "John Michael Doe",
            "body": "Excited to announce our new product launch! 🚀",
            "date": "31 January 2026",
            "time": "02:30 PM",
            "duration": "2 hours ago",
            "likesCount": 15,
            "commentsCount": 8,
            "reviews": [
                {
                    "id": 1,
                    "corpId": "CORP001",
                    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
                    "EmpCode": "EMP002",
                    "companyName": "Tech Solutions Ltd",
                    "employeeFullName": "Jane Smith",
                    "isLiked": "0",
                    "comment": "Congratulations! Looking forward to it!",
                    "date": "31 January 2026",
                    "time": "02:45 PM",
                    "duration": "1 hour ago"
                }
            ]
        }
    ]
}
```

**Empty Result (200 OK):**
```json
{
    "status": true,
    "message": "No news feeds found",
    "filters": {
        "corpId": "CORP001",
        "companyName": null,
        "startDate": "2026-01-01",
        "endDate": "2026-01-31"
    },
    "pagination": {
        "total": 0,
        "per_page": 10,
        "current_page": 1,
        "last_page": 0,
        "from": null,
        "to": null
    },
    "data": []
}
```

---

## 3. Get Single News Feed by PUID

Retrieves a specific news feed post with all reviews and likes.

**Endpoint:** `GET /api/newsfeed/{puid}`

**Query Parameters:**
- `corpId` (required): Corporate ID

**Example Request:**
```
GET /api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890?corpId=CORP001
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "News feed retrieved successfully",
    "data": {
        "id": 1,
        "corpId": "CORP001",
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "EmpCode": "EMP001",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "John Michael Doe",
        "body": "Excited to announce our new product launch! 🚀",
        "date": "31 January 2026",
        "time": "02:30 PM",
        "duration": "2 hours ago",
        "likesCount": 15,
        "commentsCount": 8,
        "reviews": [
            {
                "id": 1,
                "corpId": "CORP001",
                "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
                "EmpCode": "EMP002",
                "companyName": "Tech Solutions Ltd",
                "employeeFullName": "Jane Smith",
                "isLiked": "0",
                "comment": "Congratulations! Looking forward to it!",
                "date": "31 January 2026",
                "time": "02:45 PM",
                "duration": "1 hour ago"
            }
        ],
        "likes": [
            {
                "id": 1,
                "corpId": "CORP001",
                "EmpCode": "EMP003",
                "companyName": "Tech Solutions Ltd",
                "employeeFullName": "Bob Johnson",
                "date": "31 January 2026",
                "time": "02:35 PM",
                "duration": "1 hour ago"
            },
            {
                "id": 2,
                "corpId": "CORP001",
                "EmpCode": "EMP004",
                "companyName": "Tech Solutions Ltd",
                "employeeFullName": "Alice Williams",
                "date": "31 January 2026",
                "time": "02:40 PM",
                "duration": "1 hour ago"
            }
        ]
    }
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "News feed not found",
    "puid": "invalid-puid"
}
```

---

## 4. Update News Feed Post

Updates an existing news feed post. Only the owner can update their post.

**Endpoint:** `PUT /api/newsfeed/{puid}` or `PATCH /api/newsfeed/{puid}`

**Request Body:**
```json
{
    "corpId": "CORP001",
    "EmpCode": "EMP001",
    "body": "Updated: Excited to announce our new product launch next week! 🚀",
    "date": "2026-01-31",
    "time": "16:00:00"
}
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "News feed updated successfully",
    "data": {
        "id": 1,
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "corpId": "CORP001",
        "EmpCode": "EMP001",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "John Michael Doe",
        "body": "Updated: Excited to announce our new product launch next week! 🚀",
        "date": "2026-01-31",
        "time": "16:00:00",
        "created_at": "2026-01-31T14:30:00.000000Z",
        "updated_at": "2026-01-31T16:00:00.000000Z"
    }
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "News feed not found or you do not have permission to update it",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

---

## 5. Delete News Feed Post

Deletes a news feed post. Only the owner can delete their post. This will cascade delete all reviews and likes.

**Endpoint:** `DELETE /api/newsfeed/{puid}`

**Query Parameters:**
- `corpId` (required): Corporate ID
- `EmpCode` (required): Employee code

**Example Request:**
```
DELETE /api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890?corpId=CORP001&EmpCode=EMP001
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "News feed deleted successfully",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "News feed not found or you do not have permission to delete it",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

---

## 6. Like a News Feed Post

Like a news feed post. Each user can like a post only once.

**Endpoint:** `POST /api/newsfeed/{puid}/like`

**Request Body:**
```json
{
    "corpId": "CORP001",
    "EmpCode": "EMP002",
    "companyName": "Tech Solutions Ltd",
    "date": "2026-01-31",
    "time": "15:00:00"
}
```

**Success Response (201 Created):**
```json
{
    "status": true,
    "message": "Post liked successfully",
    "data": {
        "id": 1,
        "corpId": "CORP001",
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "EmpCode": "EMP002",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "Jane Smith",
        "date": "2026-01-31",
        "time": "15:00:00",
        "created_at": "2026-01-31T15:00:00.000000Z",
        "updated_at": "2026-01-31T15:00:00.000000Z"
    },
    "likesCount": 16
}
```

**Error Responses:**

*Already Liked (409 Conflict):*
```json
{
    "status": false,
    "message": "You have already liked this post",
    "data": {
        "id": 1,
        "corpId": "CORP001",
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "EmpCode": "EMP002",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "Jane Smith",
        "date": "2026-01-31",
        "time": "15:00:00",
        "created_at": "2026-01-31T15:00:00.000000Z",
        "updated_at": "2026-01-31T15:00:00.000000Z"
    }
}
```

*Post Not Found (404):*
```json
{
    "status": false,
    "message": "News feed not found with the provided puid",
    "puid": "invalid-puid"
}
```

---

## 7. Unlike a News Feed Post

Remove a like from a news feed post.

**Endpoint:** `DELETE /api/newsfeed/{puid}/unlike`

**Query Parameters:**
- `corpId` (required): Corporate ID
- `EmpCode` (required): Employee code

**Example Request:**
```
DELETE /api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/unlike?corpId=CORP001&EmpCode=EMP002
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "Post unliked successfully",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "likesCount": 15
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "You have not liked this post",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

---

## 8. Get All Likes for a Post

Retrieves all users who liked a specific news feed post.

**Endpoint:** `GET /api/newsfeed/{puid}/likes`

**Query Parameters:**
- `corpId` (required): Corporate ID

**Example Request:**
```
GET /api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/likes?corpId=CORP001
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "Likes retrieved successfully",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "count": 15,
    "data": [
        {
            "id": 1,
            "corpId": "CORP001",
            "EmpCode": "EMP003",
            "companyName": "Tech Solutions Ltd",
            "employeeFullName": "Bob Johnson",
            "date": "31 January 2026",
            "time": "02:35 PM",
            "duration": "2 hours ago"
        },
        {
            "id": 2,
            "corpId": "CORP001",
            "EmpCode": "EMP004",
            "companyName": "Tech Solutions Ltd",
            "employeeFullName": "Alice Williams",
            "date": "31 January 2026",
            "time": "02:40 PM",
            "duration": "2 hours ago"
        }
    ]
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "News feed not found",
    "puid": "invalid-puid"
}
```

---

## 9. Get Likes Count for a Post

Gets the total number of likes for a specific news feed post.

**Endpoint:** `GET /api/newsfeed/{puid}/likes-count`

**Query Parameters:**
- `corpId` (required): Corporate ID

**Example Request:**
```
GET /api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/likes-count?corpId=CORP001
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "Likes count retrieved successfully",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "likesCount": 15
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "News feed not found",
    "puid": "invalid-puid"
}
```

---

## 10. Add a Comment to a Post

Add a new comment to a news feed post. **Allows multiple comments from the same user** on the same post.

**Endpoint:** `POST /api/newsfeed-comments`

**Request Body:**
```json
{
    "corpId": "CORP001",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "EmpCode": "EMP002",
    "companyName": "Tech Solutions Ltd",
    "comment": "This is amazing! Can't wait to see the launch!",
    "date": "2026-01-31",
    "time": "15:30:00"
}
```

**Note:** 
- `comment` is **required** (unlike the legacy review API)
- Each call creates a **new comment** (never updates existing ones)
- Same user can add multiple comments to the same post

**Success Response (201 Created):**
```json
{
    "status": true,
    "message": "Comment added successfully",
    "data": {
        "id": 25,
        "corpId": "CORP001",
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "EmpCode": "EMP002",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "Jane Smith",
        "isLiked": "0",
        "comment": "This is amazing! Can't wait to see the launch!",
        "date": "2026-01-31",
        "time": "15:30:00",
        "created_at": "2026-01-31T15:30:00.000000Z",
        "updated_at": "2026-01-31T15:30:00.000000Z"
    },
    "commentsCount": 5
}
```

**Error Responses:**

*Validation Error (422):*
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "comment": ["The comment field is required."]
    }
}
```

*Post Not Found (404):*
```json
{
    "status": false,
    "message": "News feed not found with the provided puid",
    "puid": "invalid-puid"
}
```

---

## 11. Delete a Specific Comment

Delete a specific comment by its unique ID. Only the comment owner can delete their comment.

**Endpoint:** `DELETE /api/newsfeed-comments/{id}`

**Path Parameters:**
- `id` (required): The unique ID of the comment to delete

**Query Parameters:**
- `corpId` (required): Corporate ID
- `EmpCode` (required): Employee code (must be the comment owner)

**Example Request:**
```
DELETE /api/newsfeed-comments/25?corpId=CORP001&EmpCode=EMP002
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "Comment deleted successfully",
    "id": 25,
    "commentsCount": 4
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "Comment not found or you do not have permission to delete it",
    "id": 25
}
```

---

## 12. Get All Comments for a Post

Retrieves all comments for a specific news feed post with pagination.

**Endpoint:** `GET /api/newsfeed/{puid}/comments`

**Query Parameters:**
- `corpId` (required): Corporate ID
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 10, max: 100)

**Example Request:**
```
GET /api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/comments?corpId=CORP001&page=1&per_page=10
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "Comments retrieved successfully",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "pagination": {
        "total": 15,
        "per_page": 10,
        "current_page": 1,
        "last_page": 2,
        "from": 1,
        "to": 10
    },
    "count": 10,
    "data": [
        {
            "id": 25,
            "corpId": "CORP001",
            "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
            "EmpCode": "EMP002",
            "companyName": "Tech Solutions Ltd",
            "employeeFullName": "Jane Smith",
            "comment": "This is amazing! Can't wait to see the launch!",
            "date": "31 January 2026",
            "time": "03:30 PM",
            "duration": "2 hours ago"
        },
        {
            "id": 24,
            "corpId": "CORP001",
            "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
            "EmpCode": "EMP002",
            "companyName": "Tech Solutions Ltd",
            "employeeFullName": "Jane Smith",
            "comment": "First comment from me!",
            "date": "31 January 2026",
            "time": "02:45 PM",
            "duration": "3 hours ago"
        }
    ]
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "News feed not found",
    "puid": "invalid-puid"
}
```

---

## 13. Legacy: Create or Update Review (Backward Compatibility)

**⚠️ DEPRECATED:** Use `POST /api/newsfeed-comments` for adding new comments instead.

This endpoint updates existing reviews if the same employee already reviewed the post. Use the new comment API for adding multiple comments.

**Endpoint:** `POST /api/newsfeed-reviews`

**Request Body:**
```json
{
    "corpId": "CORP001",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "EmpCode": "EMP002",
    "companyName": "Tech Solutions Ltd",
    "isLiked": "1",
    "comment": "This is amazing! Can't wait to see the launch!",
    "date": "2026-01-31",
    "time": "15:30:00"
}
```

---

## 14. Legacy: Delete Review by PUID (Backward Compatibility)

**⚠️ DEPRECATED:** Use `DELETE /api/newsfeed-comments/{id}` for deleting specific comments instead.

Deletes a review/comment from a news feed post by puid. This deletes the first matching review for the employee.

**Endpoint:** `DELETE /api/newsfeed-reviews/{puid}`

**Query Parameters:**
- `corpId` (required): Corporate ID
- `EmpCode` (required): Employee code

**Example Request:**
```
DELETE /api/newsfeed-reviews/a1b2c3d4-e5f6-7890-abcd-ef1234567890?corpId=CORP001&EmpCode=EMP002
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "Review deleted successfully",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

---

## Common Features

### Duration Calculation
All timestamps are automatically converted to human-readable durations:
- **Less than 5 seconds:** "Just now"
- **Less than 1 minute:** "X seconds ago"
- **Less than 1 hour:** "X minutes ago"
- **Less than 24 hours:** "X hours ago"
- **1 day:** "Yesterday"
- **Less than 7 days:** "X days ago"
- **Less than 30 days:** "X weeks ago"
- **Less than 365 days:** "X months ago"
- **365+ days:** "X years ago"

### Date & Time Formatting
- **Date format:** "31 January 2026" (day month year)
- **Time format:** "02:30 PM" (12-hour format with AM/PM)

### Employee Name Resolution
All APIs automatically fetch and concatenate employee names from the `employee_details` table using `FirstName`, `MiddleName`, and `LastName`.

---

## Error Codes Summary

| Status Code | Description |
|-------------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 404 | Not Found - Resource not found |
| 409 | Conflict - Duplicate action (e.g., already liked) |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Internal Server Error - Server error occurred |

---

## Complete API Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/newsfeed` | Create a new news feed post |
| GET | `/api/newsfeed-with-reviews` | Get all news feeds with reviews, likes (supports date filter) |
| GET | `/api/newsfeed/{puid}` | Get a single news feed by puid |
| PUT/PATCH | `/api/newsfeed/{puid}` | Update a news feed post |
| DELETE | `/api/newsfeed/{puid}` | Delete a news feed post |
| POST | `/api/newsfeed/{puid}/like` | Like a news feed post |
| DELETE | `/api/newsfeed/{puid}/unlike` | Unlike a news feed post |
| GET | `/api/newsfeed/{puid}/likes` | Get all likes for a post |
| GET | `/api/newsfeed/{puid}/likes-count` | Get likes count for a post |
| **POST** | **`/api/newsfeed-comments`** | **Add a new comment (allows multiple per user)** |
| **DELETE** | **`/api/newsfeed-comments/{id}`** | **Delete a specific comment by ID** |
| **GET** | **`/api/newsfeed/{puid}/comments`** | **Get all comments for a post (paginated)** |
| POST | `/api/newsfeed-reviews` | Legacy: Create or update a review |
| DELETE | `/api/newsfeed-reviews/{puid}` | Legacy: Delete a review by puid |

---

## Testing Examples

### Complete Workflow Example

1. **Create a Post:**
```bash
curl -X POST http://your-domain/api/newsfeed \
  -H "Content-Type: application/json" \
  -d '{
    "corpId": "CORP001",
    "EmpCode": "EMP001",
    "companyName": "Tech Solutions Ltd",
    "body": "Excited to announce our new product launch! 🚀",
    "date": "2026-01-31",
    "time": "14:30:00"
  }'
```

2. **Like the Post:**
```bash
curl -X POST http://your-domain/api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/like \
  -H "Content-Type: application/json" \
  -d '{
    "corpId": "CORP001",
    "EmpCode": "EMP002",
    "companyName": "Tech Solutions Ltd",
    "date": "2026-01-31",
    "time": "15:00:00"
  }'
```

3. **Add Multiple Comments (same user can comment multiple times):**
```bash
# First comment
curl -X POST http://your-domain/api/newsfeed-comments \
  -H "Content-Type: application/json" \
  -d '{
    "corpId": "CORP001",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "EmpCode": "EMP002",
    "companyName": "Tech Solutions Ltd",
    "comment": "Congratulations! Looking forward to it!",
    "date": "2026-01-31",
    "time": "15:30:00"
  }'

# Second comment from same user
curl -X POST http://your-domain/api/newsfeed-comments \
  -H "Content-Type: application/json" \
  -d '{
    "corpId": "CORP001",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "EmpCode": "EMP002",
    "companyName": "Tech Solutions Ltd",
    "comment": "When is the launch date?",
    "date": "2026-01-31",
    "time": "15:45:00"
  }'
```

4. **Get All Comments for a Post:**
```bash
curl -X GET "http://your-domain/api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/comments?corpId=CORP001&page=1&per_page=10"
```

5. **Delete a Specific Comment by ID:**
```bash
curl -X DELETE "http://your-domain/api/newsfeed-comments/25?corpId=CORP001&EmpCode=EMP002"
```

6. **Get All Posts with Date Filter:**
```bash
curl -X GET "http://your-domain/api/newsfeed-with-reviews?corpId=CORP001&startDate=2026-01-01&endDate=2026-01-31&page=1&per_page=10"
```

7. **Get Likes Count:**
```bash
curl -X GET "http://your-domain/api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/likes-count?corpId=CORP001"
```

8. **Delete a Post:**
```bash
curl -X DELETE "http://your-domain/api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890?corpId=CORP001&EmpCode=EMP001"
```

---

## Notes

1. All APIs require valid `corpId` and `EmpCode` which must exist in the `employee_details` table.
2. The `puid` is auto-generated as UUID when creating a new post.
3. All delete operations include cascade deletes for related data.
4. Likes are unique per user per post (enforced by database constraint).
5. **Comments allow multiple entries per user per post** - use the new `/api/newsfeed-comments` endpoint.
6. To delete a specific comment, use the comment's `id` with `/api/newsfeed-comments/{id}`.
7. Date filtering uses the format `YYYY-MM-DD` (e.g., `2026-01-31`).
8. All timestamps are stored and returned in UTC.
9. The legacy `/api/newsfeed-reviews` endpoint is kept for backward compatibility but is deprecated.

---

## Version History

- **v1.1** (2026-01-31): Added multiple comments support, date filtering, and new comment management APIs.
- **v1.0** (2026-01-31): Initial release with full CRUD operations, likes, and comments functionality.
