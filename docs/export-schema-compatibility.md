# Export schema compatibility

No sanitized real Meta export is present. All compatibility below is implemented against observed structural patterns and synthetic fixtures; real-export verification remains required.

| Dataset | Parser | Synthetic fixture | Real sanitized fixture | Structural signatures | Limitations |
|---|---|---:|---:|---|---|
| Profile identity | ProfileIdentityParser | Yes | No | profile/personal-information filename plus `name` | Name-only profile exports may require confirmation |
| Messenger | MessengerThreadParser | Yes | No | top-level `participants` and `messages` arrays | Attachments are flags; raw content discarded in privacy mode |
| Friends | FriendsParser | Yes | No | `friends(_v2)`, removed/request variants | Status follows only the source collection; no inferred history |
| Comments | CommentsParser | Yes | No | `comments(_v2)`, `comments_and_replies` | Actor may remain unresolved |
| Reactions | ReactionsParser | Yes | No | `reactions(_v2)`, `likes_and_reactions` | Reaction payload is not retained as private text |
| Mentions | MentionsParser | Yes | No | `mentions(_v2)` | Only explicit arrays; no text mining |
| Tags | TagsParser | Yes | No | `tags(_v2)`, `tagged_posts` | Only explicit arrays |
| Posts | PostsParser | Yes | No | `posts(_v2)`, `your_posts` | Stored as structural activity; post text excluded |
