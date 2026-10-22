# Deduplicate Workspace Summaries by local week

Workspace Summaries are sent by a scheduled command that runs every fifteen minutes, so overlapping scheduler runs, queue retries, restarts, and timezone changes are all normal. Each delivery is a database row keyed by the schedule, the recipient, and the local calendar date of the Monday that starts the summarised week. The row is inserted with "insert or ignore", and only the run that actually inserts it dispatches the send job, so duplicates are prevented by the unique index rather than by locks or timing.

The summarised week is the half-open range from Monday 00:00 to the following Monday 00:00 in the workspace's timezone, so an event on the boundary belongs to exactly one week and weeks that cross a DST change are 167 or 169 hours long without special cases. The comparison week is found with calendar arithmetic in the same timezone, not by subtracting a duration. Keying on the local Monday date rather than the UTC instant is deliberate: the same week moves by a few hours in UTC when the timezone changes, and the local date keeps it one week. A summary becomes due at Monday 08:00 local time and stays due for 24 hours. Missed weeks are not backfilled: an instance that was down for longer only sends the latest week, and a schedule enabled mid-week waits for the next Monday.

A delivery records when it was handed to the queue. A run that finds a delivery still unqueued ten minutes after it was claimed, because the process stopped between the two steps, or one the queue rejected, dispatches it again while the week is still due; once the due window closes, an unqueued delivery is marked failed so the settings page shows it. The scheduler's overlap lock expires after ten minutes so a killed run cannot block the window.

The send job locks its delivery row while sending, re-checks that the schedule is enabled and that the recipient is still a selected current member, and records a sanitized failure message on the row when mail delivery fails. Full exception details go to the application log only. Delivery is at-least-once: a worker that crashes after the mail server accepts the message but before the row is committed can send that week again on retry.

**Consequences**

Summaries need a queue worker, the Laravel scheduler, and working mail credentials. Without a worker, deliveries stay pending and the schedule settings show that state instead of failing silently. Changing the timezone mid-week does not resend the current week, because the local Monday date is unchanged.
