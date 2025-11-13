/**
 * AspectFile JavaScript functions
 */
var AspectFile = {
    /**
     * Toggle destination fields based on destination type
     */
    toggleDestinationFields: function(destinationType) {
        // Try multiple possible ID patterns
        var bucketField = document.getElementById('campaignevent_properties_bucket_name')
            || document.querySelector('[name*="bucket_name"]');
        var networkField = document.getElementById('campaignevent_properties_network_path')
            || document.querySelector('[name*="network_path"]');

        console.log('AspectFile: toggleDestinationFields called with:', destinationType);
        console.log('AspectFile: bucketField found:', !!bucketField);
        console.log('AspectFile: networkField found:', !!networkField);

        // Get the parent form groups
        var bucketGroup = bucketField ? bucketField.closest('.form-group') : null;
        var networkGroup = networkField ? networkField.closest('.form-group') : null;

        if (destinationType === 'NETWORK') {
            // Show network path, hide bucket name
            if (bucketGroup) {
                bucketGroup.style.display = 'none';
                bucketGroup.classList.add('aspectfile-hidden');
            }
            if (networkGroup) {
                networkGroup.style.display = 'flex';
                networkGroup.classList.remove('aspectfile-hidden');
                networkGroup.classList.add('aspectfile-destination-field');
            }

            // Update required fields
            if (bucketField) bucketField.removeAttribute('required');
            if (networkField) networkField.setAttribute('required', 'required');
        } else {
            // Show bucket name, hide network path (default S3)
            if (bucketGroup) {
                bucketGroup.style.display = 'flex';
                bucketGroup.classList.remove('aspectfile-hidden');
                bucketGroup.classList.add('aspectfile-destination-field');
            }
            if (networkGroup) {
                networkGroup.style.display = 'none';
                networkGroup.classList.add('aspectfile-hidden');
            }

            // Update required fields
            if (bucketField) bucketField.setAttribute('required', 'required');
            if (networkField) networkField.removeAttribute('required');
        }
    },

    /**
     * Initialize on page load
     */
    init: function() {
        console.log('AspectFile: init called');

        // Try multiple possible ID patterns
        var destinationTypeField = document.getElementById('campaignevent_properties_destination_type')
            || document.querySelector('[name*="destination_type"]');

        console.log('AspectFile: destinationTypeField found:', !!destinationTypeField);

        if (destinationTypeField) {
            console.log('AspectFile: initial value:', destinationTypeField.value);

            // Remove previous event listener if exists
            var oldListener = destinationTypeField._aspectFileListener;
            if (oldListener) {
                destinationTypeField.removeEventListener('change', oldListener);
            }

            // Set initial state
            AspectFile.toggleDestinationFields(destinationTypeField.value);

            // Add change event listener
            var newListener = function() {
                console.log('AspectFile: destination type changed to:', this.value);
                AspectFile.toggleDestinationFields(this.value);
            };

            destinationTypeField._aspectFileListener = newListener;
            destinationTypeField.addEventListener('change', newListener);
        } else {
            console.log('AspectFile: destination type field not found, will retry');
        }
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('AspectFile: DOMContentLoaded event fired');
        AspectFile.init();
    });
} else {
    console.log('AspectFile: DOM already loaded, initializing');
    AspectFile.init();
}

// Also initialize when modal is shown (Mautic loads forms via AJAX)
if (typeof mQuery !== 'undefined') {
    mQuery(document).on('shown.bs.modal', function() {
        console.log('AspectFile: Modal shown, reinitializing');
        setTimeout(function() {
            AspectFile.init();
        }, 100);
    });

    // Also listen for when campaign builder loads events
    mQuery(document).on('campaignBuilderEventLoad', function() {
        console.log('AspectFile: Campaign event loaded, reinitializing');
        setTimeout(function() {
            AspectFile.init();
        }, 100);
    });
}

// Use MutationObserver to detect when destination_type field is added to DOM
var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length) {
            var destinationField = document.getElementById('campaignevent_properties_destination_type')
                || document.querySelector('[name*="destination_type"]');

            if (destinationField && !destinationField.hasAttribute('data-aspectfile-initialized')) {
                console.log('AspectFile: Destination field detected via MutationObserver');
                destinationField.setAttribute('data-aspectfile-initialized', 'true');
                setTimeout(function() {
                    AspectFile.init();
                }, 200);
            }
        }
    });
});

// Start observing
observer.observe(document.body, {
    childList: true,
    subtree: true
});

// Polling fallback - try to initialize every 500ms for first 5 seconds
var initAttempts = 0;
var maxAttempts = 10;
var initInterval = setInterval(function() {
    initAttempts++;

    var destinationField = document.getElementById('campaignevent_properties_destination_type')
        || document.querySelector('[name*="destination_type"]');

    if (destinationField) {
        console.log('AspectFile: Field found via polling, initializing');
        AspectFile.init();
    }

    if (initAttempts >= maxAttempts) {
        clearInterval(initInterval);
    }
}, 500);
