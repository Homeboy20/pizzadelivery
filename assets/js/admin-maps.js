/**
 * KwetuPizza Admin Maps JavaScript
 * 
 * Handles the Google Maps integration for delivery zones.
 */

// Global variables
let map;
let drawingManager;
let selectedShape;
let allShapes = [];

/**
 * Initialize the map for delivery zone drawing
 */
function initializeMap(containerId, coordinatesFieldId) {
    // Default center (can be configured in plugin settings)
    const defaultCenter = { lat: -6.8235, lng: 39.2695 }; // Dar es Salaam, Tanzania
    
    // Create the map
    map = new google.maps.Map(document.getElementById(containerId), {
        center: defaultCenter,
        zoom: 13,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        streetViewControl: false,
        fullscreenControl: true
    });
    
    // Initialize drawing manager
    drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: null,
        drawingControl: true,
        drawingControlOptions: {
            position: google.maps.ControlPosition.TOP_CENTER,
            drawingModes: [
                google.maps.drawing.OverlayType.POLYGON
            ]
        },
        polygonOptions: {
            fillColor: '#FF5722',
            fillOpacity: 0.3,
            strokeWeight: 2,
            strokeColor: '#FF5722',
            clickable: true,
            editable: true,
            zIndex: 1
        }
    });
    
    drawingManager.setMap(map);
    
    // Handle polygon complete event
    google.maps.event.addListener(drawingManager, 'polygoncomplete', function(polygon) {
        // Only allow one polygon at a time
        clearShapes();
        
        // Add the new polygon to our shapes array
        allShapes.push(polygon);
        selectedShape = polygon;
        
        // Disable drawing mode
        drawingManager.setDrawingMode(null);
        
        // Update coordinates field when polygon is modified
        updateCoordinates(polygon, coordinatesFieldId);
        
        // Add event listeners for shape editing
        google.maps.event.addListener(polygon.getPath(), 'set_at', function() {
            updateCoordinates(polygon, coordinatesFieldId);
        });
        
        google.maps.event.addListener(polygon.getPath(), 'insert_at', function() {
            updateCoordinates(polygon, coordinatesFieldId);
        });
    });
    
    // Trigger event for external scripts
    jQuery(document).trigger('kwetupizza_maps_loaded');
}

/**
 * Initialize the map with an existing zone
 */
function initializeMapWithExistingZone(containerId, coordinatesFieldId, existingCoordinates) {
    initializeMap(containerId, coordinatesFieldId);
    
    try {
        const coordinates = typeof existingCoordinates === 'string' 
            ? JSON.parse(existingCoordinates) 
            : existingCoordinates;
        
        if (Array.isArray(coordinates) && coordinates.length > 0) {
            // Convert coordinates to LatLng objects
            const polygonCoords = coordinates.map(coord => {
                return new google.maps.LatLng(coord.lat, coord.lng);
            });
            
            // Create the polygon
            const polygon = new google.maps.Polygon({
                paths: polygonCoords,
                fillColor: '#FF5722',
                fillOpacity: 0.3,
                strokeWeight: 2,
                strokeColor: '#FF5722',
                editable: true
            });
            
            polygon.setMap(map);
            allShapes.push(polygon);
            selectedShape = polygon;
            
            // Center map on polygon
            const bounds = new google.maps.LatLngBounds();
            polygonCoords.forEach(coord => bounds.extend(coord));
            map.fitBounds(bounds);
            
            // Add event listeners for shape editing
            google.maps.event.addListener(polygon.getPath(), 'set_at', function() {
                updateCoordinates(polygon, coordinatesFieldId);
            });
            
            google.maps.event.addListener(polygon.getPath(), 'insert_at', function() {
                updateCoordinates(polygon, coordinatesFieldId);
            });
        }
    } catch (e) {
        console.error('Error parsing coordinates:', e);
    }
}

/**
 * Update coordinates field with polygon path
 */
function updateCoordinates(polygon, fieldId) {
    const coordinates = [];
    const path = polygon.getPath();
    
    for (let i = 0; i < path.getLength(); i++) {
        const point = path.getAt(i);
        coordinates.push({
            lat: point.lat(),
            lng: point.lng()
        });
    }
    
    // Update the coordinates field
    document.getElementById(fieldId).value = JSON.stringify(coordinates);
}

/**
 * Clear all shapes from the map
 */
function clearShapes() {
    for (let i = 0; i < allShapes.length; i++) {
        allShapes[i].setMap(null);
    }
    allShapes = [];
    selectedShape = null;
}

/**
 * Clear map from external button
 */
function clearMap() {
    clearShapes();
}

/**
 * Generate a random color for polygons
 */
function getRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
} 