(function( $ ) {
	'use strict';

	// Snippet Editor functionality
	$(function() {
		// Only initialize on the Snippets tab
		if ( window.location.search.indexOf('tab=snippets') === -1 && window.location.search.indexOf('tab=') !== -1 ) {
			return; // Not on snippets tab, don't initialize
		}
		
		var codeEditor = null;
		var currentFilename = null;

		// WordPress Notice System
		function showWordPressNotice(message, type = 'success') {
			var allowed = { success: true, error: true, warning: true, info: true };
			if ( ! allowed[ type ] ) {
				type = 'success';
			}
			var noticeId = 'mc-notice-' + Date.now();
			var $notice = $( '<div>', {
				id: noticeId,
				'class': 'notice is-dismissible notice-' + type
			} );
			$notice.append( $( '<p>' ).text( null == message ? '' : String( message ) ) );
			$( '.wrap' ).first().prepend( $notice );
			
			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$('#' + noticeId).fadeOut(500, function() {
					$(this).remove();
				});
			}, 5000);
		}

		// Modal Error Display System
		function showModalError(message) {
			$('#mc-error-text').text(message);
			$('#mc-modal-error').show();
		}

		function hideModalError() {
			$('#mc-modal-error').hide();
		}

		// Initialize CodeMirror editor
		function initCodeEditor() {
			console.log('🔍 DEBUG: Initializing CodeMirror...');
			
			var textarea = document.getElementById('mc-snippet-editor');
			console.log('🔍 DEBUG: Textarea found:', !!textarea);
			
			if (textarea) {
				console.log('🔍 DEBUG: Creating CodeMirror instance...');
				
				// Get the selected theme from localized data
				var selectedTheme = mc_functionality_ajax.theme || 'default';
				console.log('🔍 DEBUG: Using theme:', selectedTheme);
				
				codeEditor = wp.codeEditor.initialize(textarea, {
					codemirror: {
						mode: 'php',
						lineNumbers: true,
						lineWrapping: true,
						indentUnit: 4,
						tabSize: 4,
						indentWithTabs: true,
						theme: selectedTheme
					}
				});
				console.log('🔍 DEBUG: CodeMirror instance created:', !!codeEditor);
				console.log('🔍 DEBUG: CodeMirror.codemirror exists:', !!(codeEditor && codeEditor.codemirror));
				
				// Apply theme after initialization if needed
				if (codeEditor && codeEditor.codemirror && selectedTheme !== 'default') {
					setTimeout(function() {
						codeEditor.codemirror.setOption('theme', selectedTheme);
						console.log('🔍 DEBUG: Theme applied:', selectedTheme);
					}, 100);
				}
			} else {
				console.log('🔍 DEBUG: Textarea not found!');
			}
		}

		// Make the first line (<?php) read-only and visually protected
		function makeFirstLineReadOnly() {
			if (codeEditor && codeEditor.codemirror) {
				console.log('🔍 DEBUG: Making first line read-only...');
				
				// Mark first line as read-only with visual styling
				codeEditor.codemirror.markText(
					{line: 0, ch: 0},
					{line: 0, ch: 5}, // Marks "<?php"
					{
						readOnly: true,
						css: "background-color: #f0f0f0; color: #666; font-weight: bold; border-left: 3px solid #0073aa; padding-left: 5px;"
					}
				);
				
				// Prevent editing of first line
				codeEditor.codemirror.on('beforeChange', function(cm, change) {
					if (change.from.line === 0) {
						change.cancel();
						console.log('🔍 DEBUG: Prevented edit to first line');
						return;
					}
				});
				
				console.log('🔍 DEBUG: First line protection applied');
			}
		}

		// Show modal and load snippet
		$(document).on('click', '.mc-edit-snippet', function(e) {
			e.preventDefault();
			
			var filename = $(this).data('filename');
			console.log('🔍 DEBUG: Snippet clicked:', filename);
			
			currentFilename = filename;
			
			// Clear any existing errors
			hideModalError();
			
			// Show modal
			$('#mc-snippet-editor-modal').show();
			$('#mc-editor-title').text('Edit: ' + filename);
			console.log('🔍 DEBUG: Modal shown');
			
			// Hide the new snippet form and show the editor screen
			$('#mc-new-snippet-form').removeClass('active').hide();
			$('#mc-editor-screen').addClass('active').show();
			console.log('🔍 DEBUG: Editor screen shown');
			
			// Show/hide appropriate buttons
			$('#mc-create-snippet').hide();
			$('#mc-save-snippet').show();
			
			// Initialize editor and load content
			if (!codeEditor) {
				initCodeEditor();
			}
			
			loadSnippetContent(filename);
		});

		// Add new snippet button
		$(document).on('click', '#mc-add-snippet-btn', function(e) {
			e.preventDefault();
			
			// Clear any existing errors
			hideModalError();
			
			// Show modal
			$('#mc-snippet-editor-modal').show();
			$('#mc-editor-title').text('Add New Snippet');
			
			// Show the new snippet form
			$('#mc-new-snippet-form').addClass('active').show();
			$('#mc-editor-screen').removeClass('active').hide();
			
			// Show/hide appropriate buttons
			$('#mc-create-snippet').show();
			$('#mc-save-snippet').hide();
			
			// Clear form
			$('#mc-snippet-name').val('').focus();
			$('#mc-snippet-description').val('');
			$('#mc-generated-filename').text('-');
		});

		// Real-time filename preview
		$(document).on('input', '#mc-snippet-name', function() {
			var name = $(this).val().trim();
			if (name) {
				var filename = generateFilename(name);
				$('#mc-generated-filename').text(filename);
			} else {
				$('#mc-generated-filename').text('-');
			}
		});

		// Generate filename from name
		function generateFilename(name) {
			var filename = name.toLowerCase()
				.replace(/[^a-z0-9\s-]/g, '') // Remove special characters
				.replace(/\s+/g, '-') // Replace spaces with dashes
				.replace(/-+/g, '-') // Replace multiple dashes with single dash
				.replace(/^-|-$/g, ''); // Remove leading/trailing dashes
			
			if (filename) {
				filename += '.php';
			}
			return filename;
		}

		// Create new snippet
		$(document).on('click', '#mc-create-snippet', function() {
			var name = $('#mc-snippet-name').val().trim();
			var description = $('#mc-snippet-description').val().trim();
			
			if (!name) {
				showWordPressNotice('Please enter a snippet name.', 'error');
				$('#mc-snippet-name').focus();
				return;
			}
			
			createNewSnippet(name, description);
		});

		// Create new snippet via AJAX
		function createNewSnippet(name, description) {
			console.log('🔍 DEBUG: Creating new snippet:', name, description);
			
			$.ajax({
				url: mc_functionality_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'mc_functionality_create_snippet',
					name: name,
					description: description,
					nonce: mc_functionality_ajax.nonce
				},
				success: function(response) {
					console.log('🔍 DEBUG: Create snippet response:', response);
					
					if (response.success) {
						hideModalError(); // Clear any existing errors
						
						// Switch to editor with the new content
						currentFilename = response.data.filename;
						
						// Initialize editor if not already done
						if (!codeEditor) {
							initCodeEditor();
						}
						
						// Set content
						codeEditor.codemirror.setValue(response.data.content);
						codeEditor.codemirror.refresh();
						
						// Switch to editor screen
						$('#mc-new-snippet-form').removeClass('active').hide();
						$('#mc-editor-screen').addClass('active').show();
						$('#mc-create-snippet').hide();
						$('#mc-save-snippet').show();
						
						// Check if file is disabled (new files start disabled)
						var isDisabled = response.data.is_disabled || false;
						if (isDisabled) {
							$('#mc-editor-title').text('Edit: ' + response.data.filename + ' (Disabled)');
							$('#mc-save-snippet').text('Save & Enable');
							showModalError('This file is currently disabled. Save to enable it.');
						} else {
							$('#mc-editor-title').text('Edit: ' + response.data.filename);
							$('#mc-save-snippet').text('Save Changes');
							hideModalError();
						}
						
						// Apply first line protection
						setTimeout(function() {
							makeFirstLineReadOnly();
						}, 100);
						
						// Focus on editor
						codeEditor.codemirror.focus();
						
						// Show success notice
						showWordPressNotice(response.data.message || 'Snippet created successfully!', 'success');
						
						// Show warning if validation had issues
						if (response.data.warning) {
							showWordPressNotice(response.data.warning, 'warning');
						}
					} else {
						// Show error in modal instead of WordPress notice
						var errorMessage = response.data.message || response.data || 'Error creating snippet.';
						showModalError(errorMessage);
					}
				},
				error: function() {
					showWordPressNotice('Error creating snippet. Please try again.', 'error');
				}
			});
		}

		// Load snippet content via AJAX
		function loadSnippetContent(filename) {
			console.log('🔍 DEBUG: Loading snippet content for:', filename);
			
			$.ajax({
				url: mc_functionality_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'mc_functionality_get_snippet',
					filename: filename,
					nonce: mc_functionality_ajax.nonce
				},
				success: function(response) {
					console.log('🔍 DEBUG: AJAX response received:', response);
					
					if (response.success) {
						console.log('🔍 DEBUG: Response success, content length:', response.data.content.length);
						console.log('🔍 DEBUG: CodeEditor exists:', !!codeEditor);
						console.log('🔍 DEBUG: CodeMirror exists:', !!(codeEditor && codeEditor.codemirror));
						
						// Check if file is disabled
						var isDisabled = response.data.is_disabled || false;
						console.log('🔍 DEBUG: File disabled status:', isDisabled);
						
						// Update UI based on disabled status
						if (isDisabled) {
							$('#mc-editor-title').text('Edit: ' + filename + ' (Disabled)');
							$('#mc-save-snippet').text('Save & Enable');
							showModalError('This file is currently disabled. Save to enable it.');
						} else {
							$('#mc-editor-title').text('Edit: ' + filename);
							$('#mc-save-snippet').text('Save Changes');
							hideModalError();
						}
						
						if (codeEditor && codeEditor.codemirror) {
							console.log('🔍 DEBUG: Setting content in CodeMirror...');
							codeEditor.codemirror.setValue(response.data.content);
							codeEditor.codemirror.refresh();
							
							// Set metadata values
							$('#mc-run-context').val(response.data.run_context || 'everywhere');
							$('#mc-execution-priority').val(response.data.priority || 10);
							
							console.log('🔍 DEBUG: Setting metadata - Run Context:', response.data.run_context, 'Priority:', response.data.priority);
							
							// Content loaded successfully
							console.log('🔍 DEBUG: Content loaded successfully');
							
							// Apply first line protection
							setTimeout(function() {
								makeFirstLineReadOnly();
							}, 100);
						} else {
							// If editor isn't ready, try again after a short delay
							setTimeout(function() {
								if (codeEditor && codeEditor.codemirror) {
									console.log('🔍 DEBUG: Retry - setting content in CodeMirror...');
									codeEditor.codemirror.setValue(response.data.content);
									codeEditor.codemirror.refresh();
									
									// Set metadata values
									$('#mc-run-context').val(response.data.run_context || 'everywhere');
									$('#mc-execution-priority').val(response.data.priority || 10);
									
									console.log('🔍 DEBUG: Setting metadata - Run Context:', response.data.run_context, 'Priority:', response.data.priority);
									
									// Content loaded successfully
									console.log('🔍 DEBUG: Content loaded successfully');
									
									// Apply first line protection
									setTimeout(function() {
										makeFirstLineReadOnly();
									}, 100);
								} else {
									console.log('🔍 DEBUG: CodeMirror still not ready after retry');
									showWordPressNotice('Editor not ready. Please try again.', 'error');
								}
							}, 100);
						}
					} else {
						console.log('🔍 DEBUG: Response failed:', response.data);
						showWordPressNotice(response.data || 'Error loading snippet.', 'error');
						closeModal();
					}
				},
				error: function(xhr, status, error) {
					console.log('🔍 DEBUG: AJAX error:', {xhr: xhr, status: status, error: error});
					showWordPressNotice('Error loading snippet.', 'error');
					closeModal();
				}
			});
		}

		// Save snippet
		$(document).on('click', '#mc-save-snippet', function() {
			if (currentFilename && codeEditor) {
				saveSnippetContent(currentFilename, codeEditor.codemirror.getValue());
			}
		});

		// Save snippet content via AJAX
		function saveSnippetContent(filename, content) {
			// Get metadata values
			var runContext = $('#mc-run-context').val();
			var priority = $('#mc-execution-priority').val();
			
			$.ajax({
				url: mc_functionality_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'mc_functionality_save_snippet',
					filename: filename,
					content: content,
					run_context: runContext,
					priority: priority,
					nonce: mc_functionality_ajax.nonce
				},
				success: function(response) {
					if (response.success) {
						hideModalError(); // Clear any existing errors
						showWordPressNotice(response.data.message || 'Snippet saved successfully!', 'success');
						
						// Show warning if validation had issues
						if (response.data.warning) {
							showWordPressNotice(response.data.warning, 'warning');
						}
						
						closeModal();
						location.reload();
					} else {
						// Show error in modal instead of WordPress notice
						var errorMessage = response.data.message || response.data || 'Error saving snippet.';
						showModalError(errorMessage);
					}
				},
				error: function(xhr, status, error) {
					console.log('🔍 DEBUG: AJAX error:', status, error);
					console.log('🔍 DEBUG: Response text:', xhr.responseText);
					
					// Try to parse the response as JSON to get the error message
					try {
						var response = JSON.parse(xhr.responseText);
						if (response.data && response.data.message) {
							showModalError(response.data.message);
						} else {
							showModalError('Error saving snippet: ' + (response.data || error));
						}
					} catch (e) {
						// If we can't parse JSON, show a generic error in modal
						showModalError('Error saving snippet. Please try again.');
					}
				}
			});
		}

		// Close modal
		$(document).on('click', '.mc-modal-close', function() {
			closeModal();
		});

		// Close error message
		$(document).on('click', '.mc-error-close', function() {
			hideModalError();
		});

		// Close modal function
		function closeModal() {
			$('#mc-snippet-editor-modal').hide();
			if (codeEditor) {
				codeEditor.codemirror.setValue('');
			}
			currentFilename = null;
		}

		// Toggle snippet enable/disable
		$(document).on('click', '.mc-toggle-snippet', function(e) {
			e.preventDefault();
			
			var button = $(this);
			var filename = button.data('filename');
			var currentStatus = button.data('current-status');
			
			console.log('🔍 DEBUG: Toggling snippet:', filename, 'Current status:', currentStatus);
			
			// Disable button and show loading
			button.prop('disabled', true).text('Updating...');
			
			$.ajax({
				url: mc_functionality_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'mc_functionality_toggle_snippet',
					filename: filename,
					nonce: mc_functionality_ajax.nonce
				},
				success: function(response) {
					console.log('🔍 DEBUG: Toggle response:', response);
					
					if (response.success) {
						// Update button text and data
						var newStatus = response.data.status;
						button.data('current-status', newStatus);
						
						if (newStatus === 'enabled') {
							button.text('Disable');
							button.closest('tr').find('.snippet-status').removeClass('disabled').addClass('enabled').text('Enabled');
							button.closest('tr').find('.dashicons').removeClass('dashicons-no-alt').addClass('dashicons-yes-alt').css('color', '#46b450');
						} else {
							button.text('Enable');
							button.closest('tr').find('.snippet-status').removeClass('enabled').addClass('disabled').text('Disabled');
							button.closest('tr').find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-no-alt').css('color', '#dc3232');
						}
						
						// Re-enable button
						button.prop('disabled', false);
						
						// Show success message
						showWordPressNotice(response.data.message || 'Snippet ' + newStatus + ' successfully!', 'success');
					} else {
						showWordPressNotice(response.data || 'Error toggling snippet.', 'error');
						button.prop('disabled', false).text(currentStatus === 'enabled' ? 'Disable' : 'Enable');
					}
				},
				error: function() {
					showWordPressNotice('Error toggling snippet. Please try again.', 'error');
					button.prop('disabled', false).text(currentStatus === 'enabled' ? 'Disable' : 'Enable');
				}
			});
		});
	});

})( jQuery );
