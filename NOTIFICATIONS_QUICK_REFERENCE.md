# Notifications System - Quick Reference Guide

## 🚀 Quick Start

### For Users

#### How to Search Notifications
1. Go to your notifications page
2. Use the search form at the top
3. Enter keyword or select filters
4. Click "Search" button

#### How to Mark All as Read
1. Go to your notifications page
2. Click "Mark All Read" button
3. Confirm the action
4. Page will reload with updated status

#### How to Filter Notifications
1. Select filter from dropdown (Type, Status, or Role)
2. Click "Search" button
3. View filtered results

---

## 📋 Available Filters by Role

### Admin Notifications
- **Type**: Info, Warning, Error, Success
- **Role**: Villager, ANMS, Doctor, Admin
- **Keyword**: Search title, message, recipient, problem

### ANMS Notifications
- **Type**: Info, Warning, Error, Success
- **Status**: Read, Unread, All
- **Keyword**: Search title, message, problem

### Doctor Notifications
- **Type**: Info, Warning, Error, Success
- **Status**: Read, Unread, All
- **Keyword**: Search title, message, case

### Villager Notifications
- **Type**: Info, Warning, Error, Success
- **Status**: Read, Unread, All
- **Keyword**: Search title, message, problem

---

## 🔍 Search Examples

### Find Urgent Notifications
1. Select Type: "Warning" or "Error"
2. Click Search

### Find Unread Notifications
1. Select Status: "Unread"
2. Click Search

### Find Specific Problem
1. Enter problem title or ID in keyword field
2. Click Search

### Find Notifications from Specific User (Admin Only)
1. Select Role: "Villager" (or other role)
2. Click Search

### Combined Search
1. Select Type: "Warning"
2. Select Status: "Unread"
3. Enter keyword: "urgent"
4. Click Search

---

## 🎯 Common Tasks

### Task: Review New Notifications
```
1. Go to notifications page
2. Click Status filter → Select "Unread"
3. Click Search
4. Review notifications
5. Click "Mark All Read" when done
```

### Task: Find Specific Problem Update
```
1. Go to notifications page
2. Enter problem title in keyword field
3. Click Search
4. Click on notification to view details
```

### Task: Check Error Notifications
```
1. Go to notifications page
2. Click Type filter → Select "Error"
3. Click Search
4. Review error notifications
```

### Task: Clear All Unread Notifications
```
1. Go to notifications page
2. Click "Mark All Read" button
3. Confirm action
4. All notifications marked as read
```

---

## 🔧 Troubleshooting

### Problem: No notifications showing
**Solution**: 
- Check if filters are applied
- Clear all filters by selecting "All" options
- Click Search

### Problem: Search not working
**Solution**:
- Make sure keyword is spelled correctly
- Try searching with partial words
- Clear filters and try again

### Problem: Mark All Read not working
**Solution**:
- Check internet connection
- Refresh the page
- Try again

### Problem: Flash message not showing
**Solution**:
- Wait for page to fully load
- Check if action was successful
- Refresh the page

---

## 📱 Mobile Usage Tips

### On Mobile Devices
- Search form is responsive and stacks vertically
- All buttons are touch-friendly
- Tables scroll horizontally if needed
- Filters work the same as desktop

### Best Practices
- Use filters to reduce results on small screens
- Search for specific keywords instead of scrolling
- Mark notifications as read to keep list manageable

---

## ⚡ Keyboard Shortcuts

### Desktop Browsers
- **Tab**: Navigate between form fields
- **Enter**: Submit search form
- **Esc**: Close alert messages (if supported by browser)

---

## 🎨 Visual Indicators

### Notification Types
- 🔵 **Info** (Blue badge): General information
- ⚠️ **Warning** (Yellow badge): Important alerts
- ❌ **Error** (Red badge): Critical issues
- ✅ **Success** (Green badge): Successful actions

### Notification Status
- 🔴 **Unread** (Primary badge): New notification
- ⚪ **Read** (Secondary badge): Already viewed

### Priority Indicators (ANMS)
- 🔴 **Urgent**: Red badge
- 🟠 **High**: Orange badge
- 🟡 **Medium**: Yellow badge
- 🟢 **Low**: Green badge

---

## 📊 Page Limits

All notification pages show:
- **Maximum**: 200 notifications per page
- **Default**: All notifications (up to 200)
- **Filtered**: Results matching your search criteria

---

## 🔐 Security Notes

### Safe Practices
- ✅ Always log out when done
- ✅ Don't share your login credentials
- ✅ Review notifications regularly
- ✅ Report suspicious notifications to admin

### What's Protected
- ✅ All searches are secure (SQL injection safe)
- ✅ All inputs are validated
- ✅ All outputs are escaped (XSS safe)
- ✅ Session-based authentication

---

## 💡 Pro Tips

### For Efficient Notification Management

1. **Use Status Filter First**
   - Filter by "Unread" to see only new notifications
   - Review and mark as read when done

2. **Combine Filters**
   - Use Type + Status for precise results
   - Example: Warning + Unread = New urgent items

3. **Use Keyword Search**
   - Search by problem ID for quick access
   - Search by village name to find location-specific issues

4. **Regular Cleanup**
   - Mark notifications as read regularly
   - Use search to find old notifications

5. **Bookmark Common Searches**
   - Bookmark filtered URLs for quick access
   - Example: `notifications.php?status=unread&type=warning`

---

## 📞 Support

### Need Help?
- Contact system administrator
- Check documentation in `/docs` folder
- Review this quick reference guide

### Report Issues
- Use the feedback form in the system
- Contact admin with specific error messages
- Include screenshots if possible

---

## 🔄 Updates and Changes

### Recent Changes (December 2024)
- ✅ Added comprehensive search functionality
- ✅ Added multiple filter options
- ✅ Added "Mark All Read" button
- ✅ Added flash message feedback
- ✅ Improved UI/UX design
- ✅ Enhanced security measures
- ✅ Improved performance (200 limit)

### What's New
- **Search**: Find notifications by keyword
- **Filters**: Filter by type, status, and role
- **User Control**: Manual mark as read
- **Feedback**: Flash messages for all actions
- **Count Badge**: See total notification count
- **Better Performance**: Faster page loads

---

## 📖 Related Documentation

- `NOTIFICATIONS_IMPROVEMENTS_SUMMARY.md` - Complete technical details
- `NOTIFICATIONS_BEFORE_AFTER.md` - Detailed comparison
- `/docs/user-guide.pdf` - Full user manual (if available)

---

## ✅ Quick Checklist

### Daily Tasks
- [ ] Check unread notifications
- [ ] Review urgent/warning notifications
- [ ] Mark notifications as read
- [ ] Respond to problem assignments (ANMS/Doctor)

### Weekly Tasks
- [ ] Review all notifications
- [ ] Clear old read notifications (mental note)
- [ ] Check for missed notifications

### Monthly Tasks
- [ ] Review notification patterns
- [ ] Report any issues to admin
- [ ] Provide feedback on system

---

## 🎯 Best Practices by Role

### Admin
- Review system-wide notifications daily
- Filter by role to check user-specific issues
- Use search to find specific problems
- Monitor error notifications closely

### ANMS Officer
- Check unread notifications multiple times daily
- Filter by urgent priority
- Search for specific village problems
- Mark as read after taking action

### Doctor
- Check escalated case notifications regularly
- Filter by unread to see new cases
- Search for specific patient cases
- Respond to urgent cases promptly

### Villager
- Check notifications for problem updates
- Search for your specific problems
- Read all notifications from ANMS/Doctor
- Report issues if notifications not received

---

**Last Updated**: December 2024
**Version**: 2.0
**Status**: ✅ Production Ready

---

## 🆘 Emergency Contacts

If you encounter critical issues:
1. Contact system administrator immediately
2. Document the error message
3. Note the time and action you were performing
4. Do not attempt to fix database issues yourself

---

**Remember**: This notification system is designed to help you stay informed and manage health-related issues efficiently. Use the search and filter features to save time and improve your workflow!