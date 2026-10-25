# Pinterest resource API

Binternet talks to Pinterest's private web API. Every call is a GET to:

```
https://www.pinterest.com/resource/<Resource>/get/?source_url=<page>&data=<urlencoded JSON>
```

`data` holds `{"options": {...}}`. The request must carry:

```
x-requested-with: XMLHttpRequest
x-pinterest-appstate: active
x-pinterest-source-url: <page>
x-pinterest-pws-handler: <page handler>
Referer: https://www.pinterest.com/
```

Capture date: 2026-10-25, anonymous (no login, no cookies) via
`reverse-api-engineer` on `/search/pins/?q=cat`, a pin page, a profile and a
board. Verified from a server IP.

| Resource | Used for | Key options | Response |
|-|-|-|-|
| `BaseSearchResource` | pin search | `query`, `scope: pins`, `page_size`, `bookmarks` | `data.results[]`, `bookmark` |
| `AdvancedTypeaheadResource` | search suggestions | `term`, `pin_scope: pins`, `autocomplete_request_surface`, `count` | `data.items[]` with `type: query`, `query` |
| `PinResource` | pin detail | `id`, `field_set_key: detailed` | pin object, `aggregated_pin_data.id`, `videos`, `images` |
| `UnifiedCommentsResource` | pin comments | `aggregated_pin_id`, `page_size`, `redux_normalize_feed`, `is_reversed`, `comment_featured_ids` | `data[]` with `text`, `created_at`, `user` |
| `UserResource` | profile header | `username`, `field_set_key: profile` | user object |
| `UserActivityPinsResource` | profile pins | `username`, `page_size`, `bookmarks` | `data[]`, `bookmark` |
| `BoardsResource` | profile boards | `username`, `page_size` | `data[]` |
| `BoardsFeedResource` | profile board grid | `username`, `field_set_key: profile_grid_item`, `sort`, `bookmarks` | `data[]`, `bookmark` |
| `BoardResource` | board header | `username`, `slug`, `field_set_key: detailed` | board object |
| `BoardFeedResource` | board pins | `board_id`, `page_size`, `bookmarks` | `data[]`, `bookmark` |
| `SearchTabResource` | search tabs | none | empty for anonymous visitors |

Pagination is a `bookmark` string. The first page is a GET; later pages are
POSTs to the same URL with the options (including `bookmarks`) form-encoded as
`data`, and need the anonymous `csrftoken` cookie. See `api.php` for that flow.

Tracking endpoints seen in the capture (`ApiResource`, `ApiCResource`,
`ApiSResource`, `UserExperienceResource`, `UserRegisterTrackActionResource`,
`ActivateExperimentResource`, `_/_/trace/trace/`) are not needed by Binternet.
