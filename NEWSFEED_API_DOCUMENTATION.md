# NewsFeed API Documentation

## Overview
This documentation covers all NewsFeed APIs including creating posts, reviews (comments), likes, and retrieving newsfeed data with complete examples and outputs.

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
| date | varchar(20) | Date of post |
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
Table for storing reviews/comments on news feed posts.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| corpId | varchar(10) | Corporate ID |
| puid | varchar(100) | Foreign key to news_feed.puid |
| EmpCode | varchar(20) | Employee code |
| companyName | varchar(100) | Company name |
| employeeFullName | varchar(150) | Full name of employee |
| isLiked | varchar(1) | Like status ('0' or '1') |
| comment | text | Comment text (nullable) |
| date | varchar(20) | Date of review |
| time | varchar(20) | Time of review |
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

Retrieves all news feed posts with their reviews and likes count.

**Endpoint:** `GET /api/newsfeed-with-reviews`

**Query Parameters:**
- `corpId` (required): Corporate ID
- `companyName` (optional): Filter by company name
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 10, max: 100)

**Example Request:**
```
GET /api/newsfeed-with-reviews?corpId=CORP001&companyName=Tech%20Solutions%20Ltd&page=1&per_page=10
```

**Success Response (200 OK):**
```json
{
    "status": true,
    "message": "News feeds retrieved successfully",
    "filters": {
        "corpId": "CORP001",
        "companyName": "Tech Solutions Ltd"
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
        "companyName": null
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

## 10. Create or Update Review (Comment)

Creates a new review/comment or updates an existing one. If the same employee already reviewed the post, it updates the existing review.

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

**Note:** 
- `isLiked` is optional (can be "0" or "1")
- `comment` is optional

**Success Response - New Review (201 Created):**
```json
{
    "status": true,
    "message": "Review created successfully",
    "action": "created",
    "data": {
        "id": 1,
        "corpId": "CORP001",
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "EmpCode": "EMP002",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "Jane Smith",
        "isLiked": "1",
        "comment": "This is amazing! Can't wait to see the launch!",
        "date": "2026-01-31",
        "time": "15:30:00",
        "created_at": "2026-01-31T15:30:00.000000Z",
        "updated_at": "2026-01-31T15:30:00.000000Z"
    }
}
```

**Success Response - Updated Review (200 OK):**
```json
{
    "status": true,
    "message": "Review updated successfully",
    "action": "updated",
    "data": {
        "id": 1,
        "corpId": "CORP001",
        "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "EmpCode": "EMP002",
        "companyName": "Tech Solutions Ltd",
        "employeeFullName": "Jane Smith",
        "isLiked": "1",
        "comment": "Updated comment: This is absolutely amazing!",
        "date": "2026-01-31",
        "time": "16:00:00",
        "created_at": "2026-01-31T15:30:00.000000Z",
        "updated_at": "2026-01-31T16:00:00.000000Z"
    }
}
```

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "News feed not found with the provided puid",
    "puid": "invalid-puid"
}
```

---

## 11. Delete Review (Comment)

Deletes a review/comment from a news feed post. Only the owner of the review can delete it.

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

**Error Response (404 Not Found):**
```json
{
    "status": false,
    "message": "Review not found with the provided puid, corpId, and EmpCode",
    "puid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "corpId": "CORP001",
    "EmpCode": "EMP002"
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
| GET | `/api/newsfeed-with-reviews` | Get all news feeds with reviews and likes |
| GET | `/api/newsfeed/{puid}` | Get a single news feed by puid |
| PUT/PATCH | `/api/newsfeed/{puid}` | Update a news feed post |
| DELETE | `/api/newsfeed/{puid}` | Delete a news feed post |
| POST | `/api/newsfeed/{puid}/like` | Like a news feed post |
| DELETE | `/api/newsfeed/{puid}/unlike` | Unlike a news feed post |
| GET | `/api/newsfeed/{puid}/likes` | Get all likes for a post |
| GET | `/api/newsfeed/{puid}/likes-count` | Get likes count for a post |
| POST | `/api/newsfeed-reviews` | Create or update a review/comment |
| DELETE | `/api/newsfeed-reviews/{puid}` | Delete a review/comment |

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

3. **Add a Comment:**
```bash
curl -X POST http://your-domain/api/newsfeed-reviews \
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
```

4. **Get All Posts:**
```bash
curl -X GET "http://your-domain/api/newsfeed-with-reviews?corpId=CORP001&page=1&per_page=10"
```

5. **Get Likes Count:**
```bash
curl -X GET "http://your-domain/api/newsfeed/a1b2c3d4-e5f6-7890-abcd-ef1234567890/likes-count?corpId=CORP001"
```

---

## Notes

1. All APIs require valid `corpId` and `EmpCode` which must exist in the `employee_details` table.
2. The `puid` is auto-generated as UUID when creating a new post.
3. All delete operations include cascade deletes for related data.
4. Likes are unique per user per post (enforced by database constraint).
5. Reviews can be updated by posting again with the same `puid` and `EmpCode`.
6. All timestamps are stored and returned in UTC.

---

## Version History

- **v1.0** (2026-01-31): Initial release with full CRUD operations, likes, and comments functionality.
