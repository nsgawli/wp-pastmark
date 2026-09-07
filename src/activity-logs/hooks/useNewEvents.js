import { useCallback, useEffect, useRef, useState } from 'react';

import { fetchNewLogsCount } from '../services/logsApi';

// How often to poll while the Logs page tab is visible. This is the same
// "Simple History"-style live-update behaviour: another tab (or another
// user) logs an event, and this tab's list — without ever being reloaded —
// picks up an "N new events" badge on its own.
const POLL_INTERVAL_MS = 20000;

// Polls `/logs/new-count` for events newer than the last-seen baseline,
// matching the Logs page's current search/filters, and exposes how many are
// pending as `newEventsCount`.
//
// The baseline (the highest log id this tab has already "seen") lives only
// in memory here (`latestIdRef`), reset to `null` — "not yet established" —
// whenever `search`/`filters` change, since "new" only makes sense relative
// to whatever result set the page is currently looking at. The next poll
// (fired immediately by that reset) then silently (re)establishes it
// against the new filters, since `since_id=0` always answers with count 0
// from the server (see `Logs::get_new_logs_count()`).
//
// Call `acknowledge()` once the caller has actually refreshed the list
// (e.g. the user clicked the badge) to hide it again and re-baseline the
// same way.
const useNewEvents = ({ search = '', filters = {} } = {}) => {
	const [newEventsCount, setNewEventsCount] = useState(0);

	const latestIdRef = useRef(null);
	// Guards against a slow, now-stale poll response landing after a newer
	// one has already been kicked off (e.g. by a search/filter change or
	// `acknowledge()`) and clobbering state it no longer applies to.
	const requestTokenRef = useRef(0);

	const check = useCallback(async () => {
		if (typeof document !== 'undefined' && document.hidden) {
			return;
		}

		const requestToken = ++requestTokenRef.current;

		try {
			const { count, latestId } = await fetchNewLogsCount({
				sinceId: latestIdRef.current || 0,
				search,
				filters,
			});

			if (requestToken !== requestTokenRef.current) {
				return;
			}

			if (latestIdRef.current === null) {
				// First poll for this search/filters combination: adopt the
				// server's latest matching id as the baseline rather than
				// showing a count, since everything up to it is already
				// what's on screen.
				latestIdRef.current = latestId;
				setNewEventsCount(0);
				return;
			}

			setNewEventsCount(count);
		} catch (error) {
			// Transient network/API failure — the next poll retries, so
			// there's nothing useful to do with it here.
		}
	}, [search, filters]);

	// New search/filters: the old baseline no longer applies to this result
	// set, so forget it, hide the badge, and let the immediate `check()`
	// below re-establish it against the new one.
	useEffect(() => {
		latestIdRef.current = null;
		requestTokenRef.current += 1;
		setNewEventsCount(0);
		check();
		// `check` intentionally excluded: it already changes whenever
		// `search`/`filters` do, and including it here would just run this
		// effect a second time for the same change.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [search, filters]);

	useEffect(() => {
		const intervalId = setInterval(check, POLL_INTERVAL_MS);

		// Catch up immediately on returning to this tab rather than waiting
		// out whatever's left of the poll interval — this is the "go back
		// to the log list tab and it already shows the badge" behaviour.
		const handleVisibilityChange = () => {
			if (!document.hidden) {
				check();
			}
		};

		document.addEventListener('visibilitychange', handleVisibilityChange);
		window.addEventListener('focus', check);

		return () => {
			clearInterval(intervalId);
			document.removeEventListener(
				'visibilitychange',
				handleVisibilityChange
			);
			window.removeEventListener('focus', check);
		};
	}, [check]);

	const acknowledge = useCallback(() => {
		latestIdRef.current = null;
		requestTokenRef.current += 1;
		setNewEventsCount(0);
		check();
	}, [check]);

	return {
		newEventsCount,
		acknowledge,
	};
};

export default useNewEvents;
