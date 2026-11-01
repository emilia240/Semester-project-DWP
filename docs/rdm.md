# To Be Human - Relational Data Model (RDM)
 Ctrl+Shift+V (preview)
 
## 1. How the ER became tables

| ER thing | Becomes | Why |
|---|---|---|
| Each entity (14) | One table with the same name, PK = `<Table>ID` | Rule 1 |
| 1:N relationship | FK column on the N side (the 'many' table) | `Post.UserID`, `Comment.PostID` ... |
| Total participation (double line) | FK is NOT NULL | A post must have a user |
| Partial participation | FK may be NULL | `Post.ChallengeID`: a post does not have to belong to a challenge |
| M:N relationship | Own junction table, PK = the two FKs together | `PostCategory`, `PostTag`, `PostLike`, `CommentLike`, `BoardPost`, `UserSavedBoard`, `UserCategory`, `UserChallenge`, `Follow` |
| Recursive 1:N (reply to a comment) | FK to the same table | `Comment.ParentCommentID` |
| Recursive M:N (follows) | Junction table with two FKs to `User` | `Follow(FollowerID, FollowedID)` |
| Multivalued attribute (social links) | Own table | `UserSocialLink` |
| Relationship with an attribute | The attribute goes in the junction table | `LikedAt`, `FollowedAt`, `JoinedAt`, `AddedAt`, `SavedAt` |
| Report 'is about' post / comment / user | Three nullable FKs + CHECK that exactly one is filled | `ck_report_one_target` |
| Derived things (age, like counts, hot posts, Coolest this week, maker of the week) | NOT stored. Computed in SQL views | 3NF: no derived data |

14 entities + 9 junction tables + 1 multivalued table (`UserSocialLink`) = **24 tables**.

## 2. The tables

Notation: **PK** primary key, *FK -> Table* foreign key, NULL = may be empty (all other columns are NOT NULL). Defaults in brackets.

### Role

| Column | Type | Key / null | Default |
|---|---|---|---|
| RoleID | TINYINT UNSIGNED | **PK** |  |
| RoleName | VARCHAR(20) | UNIQUE |  |

member, admin

### User

| Column | Type | Key / null | Default |
|---|---|---|---|
| UserID | INT UNSIGNED | **PK** |  |
| RoleID | TINYINT UNSIGNED | *FK -> Role* | 1 |
| Username | VARCHAR(50) | UNIQUE |  |
| FirstName | VARCHAR(110) |  |  |
| LastName | VARCHAR(110) |  |  |
| Email | VARCHAR(190) | UNIQUE |  |
| Birthdate | DATE |  |  |
| PasswordHash | VARCHAR(255) |  |  |
| CreatedAt | TIMESTAMP |  | CURRENT_TIMESTAMP |
| EmailVerifiedAt | DATETIME | NULL |  |
| IsBanned | TINYINT(1) |  | 0 |
| BannedUntil | DATETIME | NULL |  |
| HumanMadeConfirmedAt | DATETIME | NULL |  |
| GuidelinesAcceptedAt | DATETIME | NULL |  |
| Tagline | VARCHAR(80) | NULL |  |
| AboutText | TEXT | NULL |  |
| Badge | VARCHAR(30) | NULL |  |
| IsProfilePublic | TINYINT(1) |  | 1 |
| ShowFollowerCount | TINYINT(1) |  | 1 |
| ShowLikes | TINYINT(1) |  | 1 |
| NotificationsOn | TINYINT(1) |  | 1 |
| Theme | VARCHAR(10) |  | 'light' |

### UserSocialLink

| Column | Type | Key / null | Default |
|---|---|---|---|
| SocialLinkID | INT UNSIGNED | **PK** |  |
| UserID | INT UNSIGNED | *FK -> User* |  |
| Url | VARCHAR(255) |  |  |

the multivalued attribute SocialLink

### Token

| Column | Type | Key / null | Default |
|---|---|---|---|
| TokenID | INT UNSIGNED | **PK** |  |
| UserID | INT UNSIGNED | *FK -> User* |  |
| TokenHash | CHAR(64) | UNIQUE |  |
| TokenType | VARCHAR(20) |  |  |
| ExpiresAt | DATETIME |  |  |

### Category

| Column | Type | Key / null | Default |
|---|---|---|---|
| CategoryID | INT UNSIGNED | **PK** |  |
| CategoryName | VARCHAR(40) | UNIQUE |  |

### UserCategory

| Column | Type | Key / null | Default |
|---|---|---|---|
| UserID | INT UNSIGNED | **PK**, *FK -> User* |  |
| CategoryID | INT UNSIGNED | **PK**, *FK -> Category* |  |

User is interested in Category (M:N)

### Challenge

| Column | Type | Key / null | Default |
|---|---|---|---|
| ChallengeID | INT UNSIGNED | **PK** |  |
| SetUpByUserID | INT UNSIGNED | *FK -> User* |  |
| ChallengeName | VARCHAR(80) |  |  |
| Description | TEXT |  |  |
| StartDate | DATE |  |  |
| EndDate | DATE |  |  |

### UserChallenge

| Column | Type | Key / null | Default |
|---|---|---|---|
| UserID | INT UNSIGNED | **PK**, *FK -> User* |  |
| ChallengeID | INT UNSIGNED | **PK**, *FK -> Challenge* |  |
| JoinedAt | DATETIME |  | CURRENT_TIMESTAMP |

User joins Challenge (M:N)

### Post

| Column | Type | Key / null | Default |
|---|---|---|---|
| PostID | INT UNSIGNED | **PK** |  |
| UserID | INT UNSIGNED | *FK -> User* |  |
| ChallengeID | INT UNSIGNED | *FK -> Challenge*, NULL |  |
| PostTitle | VARCHAR(100) |  |  |
| PostDescription | TEXT | NULL |  |
| UploadedAt | DATETIME |  | CURRENT_TIMESTAMP |
| AltText | VARCHAR(200) | NULL |  |
| HumanMadeConfirmedAt | DATETIME |  |  |
| IsDraft | TINYINT(1) |  | 1 |
| AiCheckStatus | VARCHAR(15) |  | 'pending' |

### PostImage

| Column | Type | Key / null | Default |
|---|---|---|---|
| ImageID | INT UNSIGNED | **PK** |  |
| PostID | INT UNSIGNED | *FK -> Post* |  |
| FileName | VARCHAR(80) |  |  |
| Position | TINYINT UNSIGNED |  |  |

Position 1 = cover, the rest are step photos

### PostCategory

| Column | Type | Key / null | Default |
|---|---|---|---|
| PostID | INT UNSIGNED | **PK**, *FK -> Post* |  |
| CategoryID | INT UNSIGNED | **PK**, *FK -> Category* |  |

Post belongs to Category (M:N), 1 to 3 per post checked in PHP

### Tag

| Column | Type | Key / null | Default |
|---|---|---|---|
| TagID | INT UNSIGNED | **PK** |  |
| TagName | VARCHAR(30) | UNIQUE |  |

### PostTag

| Column | Type | Key / null | Default |
|---|---|---|---|
| PostID | INT UNSIGNED | **PK**, *FK -> Post* |  |
| TagID | INT UNSIGNED | **PK**, *FK -> Tag* |  |

Post is tagged with Tag (M:N)

### PostLike

| Column | Type | Key / null | Default |
|---|---|---|---|
| UserID | INT UNSIGNED | **PK**, *FK -> User* |  |
| PostID | INT UNSIGNED | **PK**, *FK -> Post* |  |
| LikedAt | DATETIME |  | CURRENT_TIMESTAMP |

User likes Post (M:N)

### Comment

| Column | Type | Key / null | Default |
|---|---|---|---|
| CommentID | INT UNSIGNED | **PK** |  |
| PostID | INT UNSIGNED | *FK -> Post* |  |
| UserID | INT UNSIGNED | *FK -> User* |  |
| ParentCommentID | INT UNSIGNED | *FK -> Comment*, NULL |  |
| CommentText | VARCHAR(1000) |  |  |
| CreatedAt | DATETIME |  | CURRENT_TIMESTAMP |

ParentCommentID = replies to (recursive)

### CommentLike

| Column | Type | Key / null | Default |
|---|---|---|---|
| UserID | INT UNSIGNED | **PK**, *FK -> User* |  |
| CommentID | INT UNSIGNED | **PK**, *FK -> Comment* |  |
| LikedAt | DATETIME |  | CURRENT_TIMESTAMP |

User likes Comment (M:N)

### Follow

| Column | Type | Key / null | Default |
|---|---|---|---|
| FollowerID | INT UNSIGNED | **PK**, *FK -> User* |  |
| FollowedID | INT UNSIGNED | **PK**, *FK -> User* |  |
| FollowedAt | DATETIME |  | CURRENT_TIMESTAMP |

User follows User (recursive M:N)

### Board

| Column | Type | Key / null | Default |
|---|---|---|---|
| BoardID | INT UNSIGNED | **PK** |  |
| UserID | INT UNSIGNED | *FK -> User* |  |
| BoardName | VARCHAR(50) |  |  |
| IsDefault | TINYINT(1) |  | 0 |
| IsPublic | TINYINT(1) |  | 0 |
| CreatedAt | DATETIME |  | CURRENT_TIMESTAMP |

### BoardPost

| Column | Type | Key / null | Default |
|---|---|---|---|
| BoardID | INT UNSIGNED | **PK**, *FK -> Board* |  |
| PostID | INT UNSIGNED | **PK**, *FK -> Post* |  |
| AddedAt | DATETIME |  | CURRENT_TIMESTAMP |

Board contains Post (M:N)

### UserSavedBoard

| Column | Type | Key / null | Default |
|---|---|---|---|
| UserID | INT UNSIGNED | **PK**, *FK -> User* |  |
| BoardID | INT UNSIGNED | **PK**, *FK -> Board* |  |
| SavedAt | DATETIME |  | CURRENT_TIMESTAMP |

User saves Board (M:N)

### Report

| Column | Type | Key / null | Default |
|---|---|---|---|
| ReportID | INT UNSIGNED | **PK** |  |
| ReporterID | INT UNSIGNED | *FK -> User* |  |
| HandlerID | INT UNSIGNED | *FK -> User*, NULL |  |
| PostID | INT UNSIGNED | *FK -> Post*, NULL |  |
| CommentID | INT UNSIGNED | *FK -> Comment*, NULL |  |
| ReportedUserID | INT UNSIGNED | *FK -> User*, NULL |  |
| Reason | VARCHAR(500) |  |  |
| Status | VARCHAR(15) |  | 'open' |
| CreatedAt | DATETIME |  | CURRENT_TIMESTAMP |
| ResolvedAt | DATETIME | NULL |  |

exactly one of PostID, CommentID, ReportedUserID is filled

### Notification

| Column | Type | Key / null | Default |
|---|---|---|---|
| NotificationID | INT UNSIGNED | **PK** |  |
| RecipientID | INT UNSIGNED | *FK -> User* |  |
| ActorID | INT UNSIGNED | *FK -> User* |  |
| CommentID | INT UNSIGNED | *FK -> Comment*, NULL |  |
| NotificationType | VARCHAR(10) |  |  |
| CreatedAt | DATETIME |  | CURRENT_TIMESTAMP |
| ReadAt | DATETIME | NULL |  |

comment, reply or follow; never like

### SiteSetting

| Column | Type | Key / null | Default |
|---|---|---|---|
| SiteSettingID | TINYINT UNSIGNED | **PK** |  |
| SiteName | VARCHAR(60) |  |  |
| SiteDescription | TEXT |  |  |
| RulesAndRegulations | TEXT |  |  |
| ContactEmail | VARCHAR(190) |  |  |
| ContactPhone | VARCHAR(20) | NULL |  |
| ThemeName | VARCHAR(30) |  | 'tobehuman' |
| AccentColour | VARCHAR(20) |  | 'default' |

ONE row only (SiteSettingID is always 1). Stands alone: no relationships. ThemeName and AccentColour hold a choice from a fixed list (CSS class names), never raw CSS

### FaqItem

| Column | Type | Key / null | Default |
|---|---|---|---|
| FaqID | INT UNSIGNED | **PK** |  |
| Question | VARCHAR(200) |  |  |
| Answer | TEXT |  |  |
| SortOrder | SMALLINT UNSIGNED |  |  |

stands alone

## 3. Foreign keys and delete rules (37)

| Table.column | References | ON DELETE | NULL? |
|---|---|---|---|
| User.RoleID | Role | RESTRICT | no |
| UserSocialLink.UserID | User | CASCADE | no |
| Token.UserID | User | CASCADE | no |
| UserCategory.UserID | User | CASCADE | no |
| UserCategory.CategoryID | Category | CASCADE | no |
| Challenge.SetUpByUserID | User | RESTRICT | no |
| UserChallenge.UserID | User | CASCADE | no |
| UserChallenge.ChallengeID | Challenge | CASCADE | no |
| Post.UserID | User | CASCADE | no |
| Post.ChallengeID | Challenge | SET NULL | yes |
| PostImage.PostID | Post | CASCADE | no |
| PostCategory.PostID | Post | CASCADE | no |
| PostCategory.CategoryID | Category | CASCADE | no |
| PostTag.PostID | Post | CASCADE | no |
| PostTag.TagID | Tag | CASCADE | no |
| PostLike.UserID | User | CASCADE | no |
| PostLike.PostID | Post | CASCADE | no |
| Comment.PostID | Post | CASCADE | no |
| Comment.UserID | User | CASCADE | no |
| Comment.ParentCommentID | Comment | CASCADE | yes |
| CommentLike.UserID | User | CASCADE | no |
| CommentLike.CommentID | Comment | CASCADE | no |
| Follow.FollowerID | User | CASCADE | no |
| Follow.FollowedID | User | CASCADE | no |
| Board.UserID | User | CASCADE | no |
| BoardPost.BoardID | Board | CASCADE | no |
| BoardPost.PostID | Post | CASCADE | no |
| UserSavedBoard.UserID | User | CASCADE | no |
| UserSavedBoard.BoardID | Board | CASCADE | no |
| Report.ReporterID | User | CASCADE | no |
| Report.HandlerID | User | SET NULL | yes |
| Report.PostID | Post | CASCADE | yes |
| Report.CommentID | Comment | CASCADE | yes |
| Report.ReportedUserID | User | CASCADE | yes |
| Notification.RecipientID | User | CASCADE | no |
| Notification.ActorID | User | CASCADE | no |
| Notification.CommentID | Comment | CASCADE | yes |

Why these rules:
- **CASCADE** (almost all): what a user owns goes with the user (posts, comments, likes, boards, follows, tokens, notifications). A deleted post takes its images, likes, comments, tags, board entries and reports with it.
- **RESTRICT** on `User.RoleID` (you cannot delete a role that is in use) and `Challenge.SetUpByUserID` (you cannot delete an admin who still has challenges, move or delete the challenge first).
- **SET NULL** on `Post.ChallengeID` (deleting a challenge must not delete the members' posts) and `Report.HandlerID` (the report stays when the admin who handled it is deleted).
- The database does NOT delete image files from disk. PHP must delete the files of a post (or user) before or after the delete.

## 4. Rules the database enforces (CHECK)

- `ck_site_one_row` on `SiteSetting`: `SiteSettingID = 1`, so the table can only ever hold ONE row.
- `ck_report_one_target` on `Report`: exactly one of `PostID`, `CommentID`, `ReportedUserID` is filled. Two or none is rejected.
- `ck_follow_not_self` on `Follow`: `FollowerID <> FollowedID`, nobody follows themselves.
- Duplicates are impossible in junction tables because the PK is the pair (one like per user per post).

## 5. Normalisation check (1NF, 2NF, 3NF)

Method (Soren's lecture): go through the tables one by one and ask the three questions below. The same check is in FigJam (section "1NF -> 2NF -> 3NF check").

- **1NF**: does every table have a primary key, and does every cell hold ONE value (no list, no composite attribute)? Result: all 24 tables pass. Several images, social links, categories, tags, likes are rows in their own tables, not lists in a cell.
- **2NF**: only matters when the PK has 2+ columns. Ask: does each other column need BOTH key parts? The 9 composite-PK tables are `Follow`, `UserCategory`, `PostCategory`, `PostTag`, `BoardPost`, `UserSavedBoard`, `PostLike`, `CommentLike`, `UserChallenge`. They only hold the two keys plus a date (`LikedAt`, `JoinedAt` ...), so they pass. A table with a one-column PK is in 2NF automatically.
- **3NF**: for each table ask: does any non-key column tell me another non-key column? (Soren's example: ZipCode tells City.) Result: 23 tables pass. Age is not stored (comes from `Birthdate`), like counts are not stored, role name lives once in `Role`, category and tag names live once in `Category` and `Tag`.
- **Failed 3NF: `Notification.PostID`. FIXED (decided 1 Nov).** `CommentID` already tells which post the comment belongs to, so `PostID` was found through `Comment` anyway. `Notification.PostID` is removed from the RDM (a comment or reply notification points to the comment, a new-follower notification points to nothing). In the ER the relationship Notification "is about" Post is removed, only Notification "is about" Comment stays.
- **Kept on purpose: `Comment.PostID`.** For a reply, the parent comment also tells the post, but loading all comments of one post without walking up the reply chain needs it. Explain this choice in the report.
- `SiteSetting` (one row, one column per setting) and `FaqItem` stand alone on purpose (no relationships).

## 6. Where the exam extras fit (views, triggers, indexes)

All of it is written in `sql/01_schema.sql` (tables, indexes) and `sql/03_views_triggers.sql` (views, triggers) and was tested on a real MariaDB 10.11.

**Views (need 2+, we have 3).** Calculated, nobody picks them, nothing is stored twice. Only published posts of users who are not banned and have a public profile.
- `hot_posts`: HotScore = likes + comments in the last 3 days (PHP sorts: `ORDER BY HotScore DESC`).
- `sticky_posts` ("Coolest this week"): the 4 most liked posts of the last 7 days.
- `maker_of_the_week`: the maker of the post with the most likes + saves (put on a board) in the last 7 days. One row or none.

**Triggers (need 2+, we have 4).**
1. `trg_user_default_board`: after a user is inserted, create their default board "My saved". Used by sign-up, the Save button and the Saved page.
2. `trg_comment_notify`: after a comment is inserted, notify the post owner (type `comment`) or, for a reply, the owner of the parent comment (type `reply`). Not when you answer yourself, not when the receiver has `NotificationsOn = 0`.
3. `trg_follow_notify`: after a follow is inserted, notify the followed user (type `follow`, no comment).
4. `trg_report_resolved`: before a report is updated, when `Status` changes from `open` to something else and `ResolvedAt` is empty, set it to now.
Never a notification for likes. Soren's triggers lecture is still to come: check these against it.

**Indexes (need 2+, we have 6 of our own).** Primary keys, UNIQUE keys and foreign keys are indexed by InnoDB anyway. Ours:
- `Post(UploadedAt)`: newest posts first (Explore, home feed).
- `Post(UserID, UploadedAt)`: the posts of one profile.
- `Comment(PostID, CreatedAt)`: the comments under one post.
- `PostLike(PostID, LikedAt)`: likes in a time window, used by `hot_posts` and `sticky_posts`.
- `Notification(RecipientID, ReadAt)`: the bell (unread notifications of one user).
- `Report(Status, CreatedAt)`: the admin list of open reports.
In the report, prove one with `EXPLAIN` on 1000+ test rows (with a handful of rows MariaDB may ignore the index on purpose).

**Column sizes.** Chosen per column and not 255 everywhere: short words get short columns (`TokenType` 20, `Status` 15, `NotificationType` 10, `Theme` 10), names and titles get 30-110, e-mail 190 (so the UNIQUE index fits in utf8mb4), long free text is `TEXT` or `VARCHAR(1000)` for comments. 255 is only used for the password hash (room for newer algorithms) and for URLs.

## 7. Status of the SQL

`sql/01_schema.sql` builds all 24 tables from this RDM (written by hand, commented, re-runnable, tested on MariaDB 10.11). `Token` is stored as `TokenHash CHAR(64)` (sha256), never the token itself.

## 8. Decisions and open points

1. **Decided:** `User.GuidelinesAcceptedAt DATETIME NULL` stores join step 3. Step 2 (crafts) is `UserCategory`: the app checks the user has at least one row.
2. **Decided:** `User` gets the new columns `Badge`, `ShowLikes`, `NotificationsOn` and `GuidelinesAcceptedAt` (all in `sql/01_schema.sql`).
3. **`MaterialsUsed`** is not in `Post` (it was removed from the ER).
4. **Step photos** = `PostImage` (several images per post, `Position` gives the order). Question for Soren (in FigJam).
5. **Site setting** is now ONE row with one column per thing the case lists: `SiteName`, `SiteDescription`, `RulesAndRegulations`, `ContactEmail`, `ContactPhone`, and two styling columns `ThemeName`, `AccentColour` (a choice from a fixed list, applied as a CSS class, never raw CSS because of the CSP). Who may edit it is NOT a database relationship: the admin pages check in PHP that the logged-in user has the admin role (`User.RoleID` -> `Role`), the same guard as the rest of the admin area (the case demands: password protected, session). Soren question (optional): is a one-row, no-relationship table OK in an ER?
6. **Image files on disk:** the database cannot delete them. Order in PHP: read the file names, delete the rows (in a transaction), commit, THEN `unlink()` the files. Question for Kim (in FigJam).
7. **Names:** `PostLike` and `CommentLike` because `LIKE` is an SQL keyword.
8. **No ENUM:** `NotificationType`, `Status`, `AiCheckStatus`, `TokenType` are VARCHAR checked in PHP. If Soren wants lookup tables for these too, they become small tables like `Role`.
9. `Theme` on `User` is VARCHAR(10) ('light' / 'dark').
10. **Question for Soren: `UNSIGNED`.** All ids, `Position` and `SortOrder` use `UNSIGNED` integers (`INT`, `SMALLINT`, `TINYINT UNSIGNED`). UNSIGNED means the number can never be negative, so the positive range doubles (`INT`: 0 to about 4.29 billion instead of -2.1 to +2.1 billion). It exists in MySQL/MariaDB only, not in standard SQL. Keep it, or use plain `INT` / `TINYINT`? (If he says plain: remove the word UNSIGNED from the types in the RDM and the SQL.)
11. **SQL style (1 Nov, agreed):** like the Restaurant assignment: no backticks (tested: none of the names is a reserved word), PK on the same line, comments above, multi-row `INSERT` with a column list, separate files (01 tables, 03 views and triggers). Kept on purpose: named foreign keys with `ON DELETE`, indexes, CHECKs, `ENGINE = InnoDB`. Question for Soren: `DROP DATABASE` (local) vs `DROP TABLE` per table (Simply), see the header of `01_schema.sql`.
