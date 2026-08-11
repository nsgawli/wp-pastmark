import { useEffect, useState } from 'react';
import { getEventSettings, saveEventSettings } from '../services/api';

const getActionKey = (action) => {
	if (typeof action === 'string') {
		return action;
	}

	return action?.key;
};

const getSettingsFromPreset = (events, preset = {}) => {
	const nextSettings = {};

	Object.entries(events || {}).forEach(([eventKey, event]) => {
		nextSettings[eventKey] = {};

		const actions = Array.isArray(event?.actions) ? event.actions : [];

		actions.forEach((action) => {
			const actionKey = getActionKey(action);

			if (!actionKey) {
				return;
			}

			nextSettings[eventKey][actionKey] = false;
		});
	});

	Object.entries(preset || {}).forEach(([eventKey, actions]) => {
		(actions || []).forEach((actionKey) => {
			if (nextSettings[eventKey]) {
				nextSettings[eventKey][actionKey] = true;
			}
		});
	});

	return nextSettings;
};

const isSameSettings = (left, right) => {
	const leftEvents = Object.keys(left || {});
	const rightEvents = Object.keys(right || {});

	if (leftEvents.length !== rightEvents.length) {
		return false;
	}

	for (const eventKey of leftEvents) {
		const leftActions = Object.keys(left?.[eventKey] || {});
		const rightActions = Object.keys(right?.[eventKey] || {});

		if (leftActions.length !== rightActions.length) {
			return false;
		}

		for (const actionKey of leftActions) {
			if (
				Boolean(left?.[eventKey]?.[actionKey]) !==
					Boolean(right?.[eventKey]?.[actionKey])
			) {
				return false;
			}
		}
	}

	return true;
};

const detectLogLevelFromSettings = (
	events,
	settings,
	presets
) => {
	const order = ['essential', 'recommended', 'complete'];

	for (const presetKey of order) {
		const preset = presets?.[presetKey];

		if (!preset) {
			continue;
		}

		const presetSettings = getSettingsFromPreset(events, preset);

		if (isSameSettings(settings, presetSettings)) {
			return presetKey;
		}
	}

	return 'custom';
};

const useEvents = () => {
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);

	const [events, setEvents] = useState({});
	const [settings, setSettings] = useState({});

	const [hasChanges, setHasChanges] = useState(false);

	const [presets, setPresets] = useState({});

	const [logLevel, setLogLevel] = useState('recommended');

	const loadSettings = async () => {
		setLoading(true);

		try {
			const response = await getEventSettings();
			const nextEvents = response.data.events || {};
			const nextSettings = response.data.settings || {};
			const nextPresets = response.data.presets || {};

			const resolvedLogLevel =
				typeof response.data.logLevel === 'string'
					? response.data.logLevel
					: detectLogLevelFromSettings(
							nextEvents,
							nextSettings,
							nextPresets
					  );

			setEvents(nextEvents);
			setSettings(nextSettings);
			setPresets(nextPresets);
			setLogLevel(resolvedLogLevel);
		} finally {
			setLoading(false);
		}
	};

	const saveSettings = async () => {
		setSaving(true);

		try {
			const response = await saveEventSettings(settings, logLevel);

			setSettings(response.data.settings || {});

			if (typeof response.data.logLevel === 'string') {
				setLogLevel(response.data.logLevel);
			}

			setHasChanges(false);

			return response;
		} finally {
			setSaving(false);
		}
	};

	const updateSetting = (event, action, value) => {
		setHasChanges(true);
		setLogLevel('custom');
		setSettings((prev) => ({
			...prev,
			[event]: {
				...(prev[event] || {}),
				[action]: value,
			},
		}));
	};

	useEffect(() => {
		loadSettings();
	}, []);

	const enableAllCategory = (eventKey) => {
		const event = events[eventKey];

		if (!event) {
			return;
		}

		setHasChanges(true);

		setSettings((prev) => {
			const updated = {
				...(prev || {}),
			};

			updated[eventKey] = {
				...(updated[eventKey] || {}),
			};

			event.actions.forEach((action) => {
				updated[eventKey][action] = true;
			});

			return updated;
		});
	};

	const disableAllCategory = (eventKey) => {
		const event = events[eventKey];

		if (!event) {
			return;
		}

		setHasChanges(true);

		setSettings((prev) => {
			const updated = {
				...(prev || {}),
			};

			updated[eventKey] = {
				...(updated[eventKey] || {}),
			};

			event.actions.forEach((action) => {
				updated[eventKey][action] = false;
			});

			return updated;
		});
	};

	const applyPreset = (presetKey) => {
		if (!presets[presetKey]) {
			return;
		}

		const updated = getSettingsFromPreset(events, presets[presetKey]);

		setSettings(updated);
		setLogLevel(presetKey);
		setHasChanges(true);
	};

	return {
		loading,
		saving,
		hasChanges,
		events,
		settings,
		updateSetting,
		saveSettings,
		enableAllCategory,
		disableAllCategory,
		presets,
		logLevel,
		applyPreset,
		setLogLevel,
		reload: loadSettings,
	};
};

export default useEvents;
