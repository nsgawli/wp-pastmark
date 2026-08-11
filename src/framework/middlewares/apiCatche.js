// Cache initialization
const cache = {
	data: new Map(), // Stores the data of resolved API calls
	promises: new Map(), // Stores the promises of ongoing API calls
};

// Generate a unique key for each request based on the path and parameters
const generateCacheKey = (options) =>
	`${options.path}-${JSON.stringify(options.data || {})}`;

// Middleware function
const apiCache = (options, next) => {
	// return if the cache is disabled
	if (!options.useApiCache) {
		return next(options);
	}

	// Generate a unique key for the request
	const key = generateCacheKey(options);

	// Check if the data is already cached and return it
	if (cache.data.has(key)) {
		return Promise.resolve(cache.data.get(key));
	}

	// If a request is in progress, return the existing promise
	if (cache.promises.has(key)) {
		return cache.promises.get(key);
	}

	// Proceed with the API call and cache the promise
	const promise = next(options)
		.then((result) => {
			cache.data.set(key, result); // Cache the result
			cache.promises.delete(key); // Remove the promise from the cache
			return result;
		})
		.catch((error) => {
			cache.promises.delete(key); // Ensure to clean up even in case of an error
			throw error;
		});

	cache.promises.set(key, promise);

	return promise;
};

// Add a method to clear cache entries by pattern
apiCache.clear = (pattern) => {
	for (const key of cache.data.keys()) {
		if (key.includes(pattern)) {
			cache.data.delete(key);
		}
	}
	for (const key of cache.promises.keys()) {
		if (key.includes(pattern)) {
			cache.promises.delete(key);
		}
	}
};

export default apiCache;
