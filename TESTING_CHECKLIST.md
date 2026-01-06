# Liveblog Testing Checklist - PR #3 (React 18 + @wordpress/scripts)

## Setup Test Environment

1. **Start WordPress environment:**
   ```bash
   wp-env start
   ```
   This creates a local WordPress install at http://localhost:8888

2. **Login to WordPress Admin:**
   - URL: http://localhost:8888/wp-admin
   - Username: `admin`
   - Password: `password`

3. **Build the plugin (BEFORE changes):**
   ```bash
   npm run build
   ```

---

## BEFORE Changes - Test These Features

### 1. Admin Area Tests

**1.1 Enable Liveblog on a Post**
- [ ] Go to Posts → Add New
- [ ] Create a test post with title "Test Liveblog Event"
- [ ] In the right sidebar, find the "Liveblog" meta box
  - If you don't see it, click "Screen Options" (top right) and enable "Liveblog"
- [ ] Click "Enable" in the Liveblog meta box
- [ ] **Expected:** Liveblog settings appear (Key Events Template, Key Events Format, State dropdown)
- [ ] Publish the post

**1.2 Liveblog Settings**
- [ ] In the Liveblog meta box, check these settings work:
  - [ ] State dropdown (Enabled/Disabled/Archived)
  - [ ] Key Events Template dropdown
  - [ ] Key Events Format dropdown
- [ ] **Expected:** All dropdowns functional, no JavaScript errors in console

---

### 2. Frontend Editor Tests (Most Important - This Uses React/draft-js)

**2.1 Access the Editor**
- [ ] View your published liveblog post on the frontend (http://localhost:8888/...)
- [ ] As logged-in admin, you should see the "New Entry" editor box at the top
- [ ] **Expected:** Editor loads, no JavaScript errors in browser console

**2.2 Create a Basic Entry**
- [ ] Type some text in the editor: "This is my first test entry"
- [ ] Click "Publish Update"
- [ ] **Expected:**
  - Entry appears at the top of the page
  - Entry has a timestamp (e.g., "a few seconds ago")
  - No errors in console

**2.3 Rich Text Formatting**
- [ ] Create a new entry with HTML formatting:
  ```
  <strong>Bold text</strong> and <em>italic text</em> and <u>underlined</u>
  ```
- [ ] Click "Publish Update"
- [ ] **Expected:** Text renders with formatting applied

**2.4 Add a Link**
- [ ] Create an entry with: `Check out https://wordpress.org`
- [ ] **Expected:** Link is automatically hyperlinked

**2.5 Add an Image (Drag & Drop)**
- [ ] Drag an image file from your desktop into the editor
- [ ] **Expected:**
  - Image uploads
  - Image preview appears in editor
  - Can click "Publish Update" successfully

**2.6 Embed Media**
- [ ] Create entry with a YouTube URL on its own line:
  ```
  https://www.youtube.com/watch?v=dQw4w9WgXcQ
  ```
- [ ] **Expected:** YouTube video embeds in the published entry

**2.7 Use Hashtags**
- [ ] Create entry: `This is about #wordpress and #liveblog`
- [ ] **Expected:** Hashtags are styled/highlighted

**2.8 Use /key Command**
- [ ] Create entry: `Major announcement! /key`
- [ ] **Expected:** Entry is marked as "key event" (special styling)

**2.9 Use Emoji**
- [ ] Create entry: `Great event :thumbsup: :smile:`
- [ ] **Expected:** Emoji codes convert to emoji images

**2.10 Edit an Entry**
- [ ] Find an existing entry, click "Edit"
- [ ] Modify the text
- [ ] Click "Update"
- [ ] **Expected:** Entry updates successfully

**2.11 Delete an Entry**
- [ ] Click "Delete" on an entry
- [ ] Confirm deletion
- [ ] **Expected:** Entry is removed

**2.12 Preview an Entry**
- [ ] Type text in editor
- [ ] Click "Preview" (don't publish)
- [ ] **Expected:** Preview appears showing how entry will look

---

### 3. Frontend Display Tests

**3.1 View as Non-Logged-In User**
- [ ] Open the liveblog post in an incognito/private browser window
- [ ] **Expected:**
  - Entries display correctly
  - No editor visible (since not logged in)
  - Timestamps show ("2 minutes ago", etc.)
  - No JavaScript errors

**3.2 Auto-Refresh (Smart Updates)**
- [ ] Open post in two browser windows (one logged in, one not)
- [ ] In logged-in window, create a new entry
- [ ] **Expected:** Entry appears in the other window automatically after a few seconds

**3.3 Scroll Behavior**
- [ ] In the viewing window, scroll down (away from top)
- [ ] In the editing window, create a new entry
- [ ] **Expected:**
  - New entry doesn't auto-load at top
  - Notification bar appears: "New updates available"
  - Clicking the bar loads new entries

---

### 4. Archive Mode

**4.1 Archive a Liveblog**
- [ ] In WordPress admin, edit your liveblog post
- [ ] Change Liveblog State to "Archived"
- [ ] Save post
- [ ] View post on frontend
- [ ] **Expected:**
  - Entries still visible
  - No editor visible
  - No auto-refresh happening
  - Message indicates liveblog is archived

---

### 5. Console & Build Tests

**5.1 JavaScript Console**
- [ ] Open browser DevTools Console (F12)
- [ ] Navigate through the liveblog
- [ ] **Expected:** No JavaScript errors (warnings are OK)

**5.2 Check Built Assets**
- [ ] Verify these files exist and load:
  - [ ] `assets/app.js`
  - [ ] `assets/app.css`
  - [ ] `assets/amp.js`
  - [ ] `assets/amp.css`

---

## Testing Notes Template

Use this template to record your BEFORE test results:

```
BEFORE CHANGES - Test Results
Date: [DATE]
Browser: [Chrome/Firefox/Safari]

Admin Tests:
- Enable liveblog: [ PASS / FAIL / NOTES ]
- Settings work: [ PASS / FAIL / NOTES ]

Editor Tests (Critical):
- Editor loads: [ PASS / FAIL / NOTES ]
- Create entry: [ PASS / FAIL / NOTES ]
- Rich text: [ PASS / FAIL / NOTES ]
- Links: [ PASS / FAIL / NOTES ]
- Images: [ PASS / FAIL / NOTES ]
- Embeds: [ PASS / FAIL / NOTES ]
- Hashtags: [ PASS / FAIL / NOTES ]
- /key command: [ PASS / FAIL / NOTES ]
- Emoji: [ PASS / FAIL / NOTES ]
- Edit entry: [ PASS / FAIL / NOTES ]
- Delete entry: [ PASS / FAIL / NOTES ]
- Preview: [ PASS / FAIL / NOTES ]

Frontend Tests:
- Display works: [ PASS / FAIL / NOTES ]
- Auto-refresh: [ PASS / FAIL / NOTES ]
- Smart updates: [ PASS / FAIL / NOTES ]

Archive Tests:
- Archive mode: [ PASS / FAIL / NOTES ]

Console:
- No errors: [ PASS / FAIL / ERROR MESSAGES ]
```

---

## After Making Changes

Run this exact same checklist again to ensure nothing broke!

## Quick Smoke Test (Minimum)

If you're short on time, at minimum test these:
1. ✅ Editor loads without errors
2. ✅ Can create a basic entry
3. ✅ Can edit an entry
4. ✅ No console errors
5. ✅ Build completes: `npm run build`
