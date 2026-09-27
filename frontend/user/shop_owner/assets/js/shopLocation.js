
(function () {
    const form = document.getElementById('shopProfileForm');
    const editButton = document.getElementById('editShopProfile');
    const saveButton = document.getElementById('saveShopProfile');
    const editControls = form ? form.querySelectorAll('[data-edit-control]') : [];

    const setLocationButton = document.getElementById('setShopLocation');
    const latitudeInput = document.getElementById('shopLatitude');
    const longitudeInput = document.getElementById('shopLongitude');
    const addressInput = document.getElementById('shop_address');
    const coordinateNote = document.getElementById('shopCoordinateNote');
    const mapElement = document.getElementById('ownerShopMap');

    const defaultLat = 12.0432;
    const defaultLng = 124.5946;

    const savedLat = latitudeInput && latitudeInput.value !== '' ? parseFloat(latitudeInput.value) : null;
    const savedLng = longitudeInput && longitudeInput.value !== '' ? parseFloat(longitudeInput.value) : null;
    const hasSavedPin = Number.isFinite(savedLat) && Number.isFinite(savedLng);

    let locationEditEnabled = false;
    let map = null;
    let marker = null;
    let locationBusy = false;

    if (!form || !editButton || !saveButton) {
        return;
    }

    function currentScriptBaseUrl() {
        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++) {
            const src = scripts[i].getAttribute('src') || '';
            if (src.indexOf('shopLocation.js') !== -1) {
                const base = scripts[i].getAttribute('data-base-url');
                if (base) return base.replace(/\/$/, '') + '/';
            }
        }
        return '/';
    }

    const baseUrl = currentScriptBaseUrl();
    const reverseGeocodeUrl = baseUrl + 'backend/actions/reverse_geocode.php';

    function updateCoordinateFields(latlng) {
        latitudeInput.value = latlng.lat.toFixed(8);
        longitudeInput.value = latlng.lng.toFixed(8);

        if (coordinateNote) {
            coordinateNote.textContent = 'Pin selected at ' + latitudeInput.value + ', ' + longitudeInput.value;
        }
    }

    async function reverseGeocode(latlng) {
        if (!addressInput) return;

        try {
            const response = await fetch(
                reverseGeocodeUrl + '?lat=' + encodeURIComponent(latlng.lat) + '&lng=' + encodeURIComponent(latlng.lng)
            );

            const data = await response.json();

            if (data && data.success && data.address) {
                addressInput.value = data.address;
            } else {
                if (coordinateNote) {
                    coordinateNote.textContent = 'Pin selected, but address was not detected. You may type the address manually.';
                }
            }
        } catch (error) {
            if (coordinateNote) {
                coordinateNote.textContent = 'Pin selected, but address was not detected. You may type the address manually.';
            }
        }
    }

    function placeMarker(latlng, detectAddress = true) {
        if (!marker) {
            marker = L.marker(latlng, { draggable: locationEditEnabled }).addTo(map);

            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                updateCoordinateFields(pos);
                reverseGeocode(pos);
            });
        } else {
            marker.setLatLng(latlng);
        }

        if (marker.dragging) {
            marker.dragging[locationEditEnabled ? 'enable' : 'disable']();
        }

        updateCoordinateFields(latlng);

        if (detectAddress) {
            reverseGeocode(latlng);
        }
    }

    if (mapElement && window.L) {
        const initialCenter = hasSavedPin ? [savedLat, savedLng] : [defaultLat, defaultLng];

        map = L.map(mapElement, {
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            touchZoom: false
        }).setView(initialCenter, hasSavedPin ? 16 : 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (hasSavedPin) {
            placeMarker(L.latLng(savedLat, savedLng), false);
        }

        map.on('click', function (event) {
            if (locationEditEnabled) {
                placeMarker(event.latlng, true);
            }
        });

        setTimeout(function () {
            map.invalidateSize();
        }, 150);
    }

    function enableLocationEditing() {
        locationEditEnabled = true;

        if (map) {
            map.dragging.enable();
            map.scrollWheelZoom.enable();
            map.doubleClickZoom.enable();
            map.touchZoom.enable();

            if (marker && marker.dragging) {
                marker.dragging.enable();
            }

            map.invalidateSize();
        }

        if (coordinateNote && !latitudeInput.value) {
            coordinateNote.textContent = 'Use current location or click the map to place the shop pin manually.';
        }
    }

    function locationErrorMessage(error) {
        if (!error) {
            return 'Your device location is unavailable. Click the map manually.';
        }

        if (error.code === error.PERMISSION_DENIED) {
            return 'Location permission is blocked. Enable it from browser site settings, then click Use My Current Location again. You can still click the map manually.';
        }

        if (error.code === error.TIMEOUT) {
            return 'Location request timed out. Try again or click the map manually.';
        }

        return 'Your device location is unavailable. Click the map manually.';
    }

    function detectCurrentLocation() {
        if (locationBusy) return;

        if (!navigator.geolocation) {
            coordinateNote.textContent = 'GPS is not supported by this browser. Click the map manually.';
            return;
        }

        locationBusy = true;
        coordinateNote.textContent = 'Getting your current shop location...';

        navigator.geolocation.getCurrentPosition(
            function (position) {
                locationBusy = false;
                const latlng = L.latLng(
                    position.coords.latitude,
                    position.coords.longitude
                );

                map.setView(latlng, 17);
                placeMarker(latlng, true);
            },
            function (error) {
                locationBusy = false;
                coordinateNote.textContent = locationErrorMessage(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    function requestCurrentLocation() {
        detectCurrentLocation();
    }

    editButton.addEventListener('click', function () {
        form.classList.remove('is-locked');
        form.classList.add('is-editing');

        form.querySelectorAll('[data-editable]').forEach(function (field) {
            field.disabled = false;
        });

        editControls.forEach(function (control) {
            control.classList.remove('is-disabled');
        });

        saveButton.disabled = false;
        editButton.disabled = true;
        editButton.classList.add('is-disabled');

        enableLocationEditing();
    });

    if (setLocationButton) {
        setLocationButton.addEventListener('click', function () {
            enableLocationEditing();
            requestCurrentLocation();
        });
    }
})();
