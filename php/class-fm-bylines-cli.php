<?php
WP_CLI::add_command( 'fm-bylines', 'FM_Bylines_CLI' );

class FM_Bylines_CLI extends WP_CLI_Command {

	/**
	 * Migrate Co-Authors Plus Guest Authors and linked accounts to FM Bylines
	 *
	 * @subcommand migrate_coauthors
	 */
	public function migrate_coauthors( $args, $assoc_args ) {
		global $coauthors_plus;

		if ( ! empty( $coauthors_plus->coauthor_taxonomy ) ) {

			$cap_terms = get_terms( $coauthors_plus->coauthor_taxonomy, array( 'hide_empty' => false ) );

			if ( ! is_wp_error( $cap_terms ) && ! empty( $cap_terms ) ) {

				WP_CLI::line( 'Migrating Co-Authors Plus guest-authors to bylines:' );

				foreach ( $cap_terms as $cap_term ) {
					// Reset all the vars, just in case.
					$cap_data = false;
					$cap_email = false;
					$cap_post = false;
					$cap_meta = false;
					$mapped_user = false;
					$byline_slug = false;
					$byline_post = false;
					$objects = false;
					$byline_display_name = false;

					// Parse the author data out of the CAP description.
					$cap_data = explode( ' ', $cap_term->description );
					$cap_email = array_pop( $cap_data );

					// Check if there is CAP Post.
					$args = array(
						'post_type' => $coauthors_plus->guest_authors->post_type,
						'name' => $cap_term->slug,
						'post_status' => 'publish',
						'numberposts' => 1,
					);
					$cap_post = get_posts( $args );
					if ( ! empty( $cap_post[0]->ID ) ) {
						$cap_meta = apply_filters( 'fm_bylines_cli_cap_meta', get_post_meta( $cap_post[0]->ID ), $cap_post[0], $cap_term );
					} else {
						$cap_meta = false;
					}

					// Check to see if there is a user connected to CAP.
					if ( ! empty( $cap_meta ) && ! empty( $cap_meta['cap-linked_account'][0] ) ) {
						$mapped_user = get_user_by( 'slug', $cap_meta['cap-linked_account'][0] );
					}

					// No mapped user, lets check by email.
					if ( empty( $mapped_user ) ) {
						$mapped_user = get_user_by( 'email', $cap_email );
					}

					// Check to see if a byline exists by slug.
					$byline_slug = $cap_term->name;
					$args = array(
						'post_type' => FM_Bylines()->name,
						'name' => $byline_slug,
						'post_status' => 'publish',
						'numberposts' => 1,
					);
					$byline_post = get_posts( $args );

					// Check for byline by mapped user.
					if ( empty( $byline_post[0]->ID ) && ! empty( $mapped_user->ID ) ) {
						$args = array(
							'post_type' => FM_Bylines()->name,
							'meta_key' => 'fm_bylines_user_mapping',
							'meta_value' => $mapped_user->ID,
							'post_status' => 'publish',
							'numberposts' => 1,
						);
						$byline_post = get_posts( $args );
					}

					// Create a byline.
					if ( empty( $byline_post[0]->ID ) ) {
						// Generate the name of the byline.
						if ( ! empty( $cap_meta['cap-display_name'][0] ) ) {
							$byline_display_name = $cap_meta['cap-display_name'][0];
						}
						if ( ! empty( $mapped_user->display_name ) ) {
							if ( empty( $byline_display_name ) || strpos( $byline_display_name, '-' ) != false ) {
								$byline_display_name = $mapped_user->display_name;
								if ( empty( $byline_display_name ) ) {
									$byline_display_name = $mapped_user->display_name;
								}
							}
						}
						if ( empty( $byline_display_name ) ) {
							$byline_display_name = $cap_term->slug;
						}

						// Create a byline post.
						$byline_args = array(
							'post_title' => $byline_display_name,
							'post_name' => $byline_slug,
							'post_type' => FM_Bylines()->name,
							'post_status' => 'publish',
						);
						$byline_id = wp_insert_post( $byline_args );
						if ( ! empty( $byline_id ) ) {
							// Set byline metadata to CAP metadata.
							// You can use the filter hook fm_bylines_cli_cap_meta to add in the missing meta fields such as twitter, linkedin & shortbio if they exist in the CAP metadata.
							update_post_meta( $byline_id, 'fm_bylines_names', array(
								'first_name' => ! empty( $cap_meta['cap-first_name'][0] ) ? $cap_meta['cap-first_name'][0] : '',
								'last_name' => ! empty( $cap_meta['cap-first_name'][0] ) ? $cap_meta['cap-last_name'][0] : '',
							) );

							update_post_meta( $byline_id, 'fm_bylines_contact_info', array(
								'email' => ! empty( $cap_meta['cap-user_email'][0] ) ? $cap_meta['cap-user_email'][0] : '',
								'website' => ! empty( $cap_meta['cap-website'][0] ) ? $cap_meta['cap-website'][0] : '',
								'twitter' => ! empty( $cap_meta['cap-twitter'][0] ) ? $cap_meta['cap-twitter'][0] : '',
								'linkedin' => ! empty( $cap_meta['cap-linkedin'][0] ) ? $cap_meta['cap-linkedin'][0] : '',
							) );

							update_post_meta( $byline_id, 'fm_bylines_about', array(
								'bio' => ! empty( $cap_meta['cap-description'][0] ) ? $cap_meta['cap-description'][0] : '',
								'short-bio' => ! empty( $cap_meta['cap-shortbio'][0] ) ? $cap_meta['cap-shortbio'][0] : '',
							) );

							if ( ! empty( $mapped_user->ID ) ) {
								update_post_meta( $byline_id, 'fm_bylines_user_mapping', $mapped_user->ID );
							}

							// Update CAP post with Bylines ID.
							if ( ! empty( $cap_post[0]->ID ) && ! empty( $byline_id ) ) {
								update_post_meta( $cap_post[0]->ID, 'fm_bylines_id', $byline_id );
							}
						} else {
							WP_CLI::line( "Falied to insert byline: {$byline_slug}" );
						}
					} else {
						$byline_id = $byline_post[0]->ID;
					}

					if ( ! empty( $cap_post[0]->ID ) ) {
						do_action( 'fm_bylines_cli_post_cap_migration', $byline_id, $cap_meta, $cap_term, $cap_post[0] );
					} else {
						do_action( 'fm_bylines_cli_post_cap_migration', $byline_id, $cap_meta, $cap_term, false );
					}

					if ( ! empty( $cap_meta ) ) {
						// Copy cap meta.
						foreach ( $cap_meta as $key => $value ) {
							update_post_meta( $byline_id, $key, $value[0] );
						}
					}

					// Update posts so byline authors reflect CAP authors.
					$objects = get_objects_in_term( $cap_term->term_id, $coauthors_plus->coauthor_taxonomy );
					foreach ( $objects as $object_id ) {
						if ( get_post_type( $object_id ) !== $coauthors_plus->guest_authors->post_type ) {
							$current_bylines = empty( get_post_meta( $object_id, 'fm_bylines_' . $coauthors_plus->coauthor_taxonomy, true ) ) ? array() : get_post_meta( $byline_id, 'fm_bylines_author', true );
							$new_byline_entry = array(
								'byline_id' => $byline_id,
								'fm_byline_type' => $coauthors_plus->coauthor_taxonomy,
							);
							$current_bylines[] = $new_byline_entry;
							update_post_meta( $object_id, 'fm_bylines_' . $coauthors_plus->coauthor_taxonomy, $current_bylines );
							update_post_meta( $object_id, 'fm_bylines_' . $coauthors_plus->coauthor_taxonomy . '_' . $byline_id, count( $current_bylines ) );
						}

					}

					WP_CLI::line( "Migrated {$cap_term->slug}" );
				}
			} else {
				WP_CLI::line( 'No Co-Authors Plus data found. Terminating.' );
			}
		} else {
			WP_CLI::line( 'Please activate enable Co-Authors Plus. Terminating.' );
		}
		WP_CLI::success( 'Migration complete' );
	}


	/**
	 * Migrate WP Users to FM Bylines
	 *
	 * @subcommand migrate_wp_users
	 * @synopsis [--batch_size]
	 */
	public function migrate_wp_users( $args, $assoc_args ) {
		// Batch size.
		if ( ! empty( $assoc_args['batch_size'] ) ) {
			$batch_size = (int) $assoc_args['batch_size'];
		} else {
			$batch_size = 10;
		}

		$wp_users = get_users( 'number=1000' );
		if ( ! is_wp_error( $wp_users ) && ! empty( $wp_users ) ) {

			WP_CLI::line( 'Migrating WP users to bylines:' );

			foreach ( $wp_users as $user ) {
				$user_data = $user->data;
				echo 'Migrating user: ' . $user->data->display_name . "\n";

				// Check by user_id to see if a byline exists for this WP User already.
				$args = array(
					'post_type' => FM_Bylines()->name,
					'meta_key' => 'fm_bylines_user_mapping',
					'meta_value' => $user_data->ID,
					'post_status' => 'publish',
					'numberposts' => 1,
				);
				$byline_post = get_posts( $args );

				if ( empty( $byline_post[0]->ID ) ) {
					// Also check by name to see if a byline exists for this WP User already.
					$byline_slug = sanitize_title_with_dashes( $user_data->display_name );
					$args = array(
						'post_type' => FM_Bylines()->name,
						'name' => $byline_slug,
						'post_status' => 'publish',
						'numberposts' => 1,
					);
					$byline_post = get_posts( $args );

					// Check to see that the user has published posts.
					$args = array(
						'author' => $user_data->ID,
					);
					$user_posts = get_posts( $args );
					if ( empty( $byline_post[0]->ID ) && ! empty( $user_posts[0]->ID ) ) {
						// Create a byline post.
						$byline_args = array(
							'post_title' => $user_data->display_name,
							'post_name' => $byline_slug,
							'post_type' => FM_Bylines()->name,
							'post_status' => 'publish',
						);
						$byline_id = wp_insert_post( $byline_args );
						if ( is_wp_error( $byline_id ) || empty( $byline_id ) ) {
							WP_CLI::line( "Falied to insert byline: {$byline_slug}" );
						} else {
							// Link the WP User to the Byline.
							add_post_meta( $byline_id, 'fm_bylines_user_mapping', $user_data->ID );
						}
					}
				} else {
					$byline_id = $byline_post[0]->ID;
				}

				// Loop through all the posts from that user and apply the byline.
				if ( ! empty( $byline_id ) ) {
					$offset = 0;
					while ( ! isset( $complete ) ) {
						$posts = get_posts(
							array(
								'posts_per_page' => $batch_size,
								'offset' => $offset,
								'author' => $user_data->ID,
							)
						);
						$offset += $batch_size;
						if ( ! empty( $posts ) ) {
							foreach ( $posts as $post ) {
								// Check that the byline is empty, then add it.
								$byline_set = get_post_meta( $post->ID, 'fm_bylines_author_' . (string) $byline_id, true );
								if ( empty( $byline_set ) ) {
									// Add the byline to this post.
									$current_bylines = empty( get_post_meta( $post->ID, 'fm_bylines_author', true ) ) ? array() : get_post_meta( $post->ID, 'fm_bylines_author', true );
									$new_byline_entry = array(
										'byline_id' => $byline_id,
										'fm_byline_type' => 'author',
									);
									$current_bylines[] = $new_byline_entry;
									update_post_meta( $post->ID, 'fm_bylines_author', $current_bylines );
									update_post_meta( $post->ID, 'fm_bylines_author_' . $byline_id, count( $current_bylines ) );
								}
							}
						} else {
							$complete = true;
						}
					}

					// Update the byline data from the user.
					$byline_contact_info = empty( get_post_meta( $byline_id, 'fm_bylines_contact_info', true ) ) ? array() : get_post_meta( $byline_id, 'fm_bylines_contact_info', true );
					if ( empty( $byline_contact_info['email'] ) ) {
						$byline_contact_info['email'] = $user_data->user_email;
						print_r( 'here' . $user_data->user_email );
					}
					if ( empty( $byline_contact_info['website'] ) ) {
						$byline_contact_info['website'] = ( ! empty( $user_data->user_url ) && $user_data->user_url != 'http://' ) ? $user_data->user_url : '';
					}
					if ( empty( $byline_contact_info['twitter'] ) ) {
						$byline_contact_info['twitter'] = '';
					}
					if ( empty( $byline_contact_info['linkedin'] ) ) {
						$byline_contact_info['linkedin'] = '';
					}
					// Add meta data.
					update_post_meta( $byline_id, 'fm_bylines_contact_info', $byline_contact_info );

					$bio = get_the_author_meta( 'description', $user_data->ID );
					$byline_about = empty( get_post_meta( $byline_id, 'fm_bylines_about', true ) ) ? array() : get_post_meta( $byline_id, 'fm_bylines_about', true );
					if ( empty( $byline_about['bio'] ) ) {
						$byline_about['bio'] = ! empty( $bio ) ? $bio : '';
					}
					if ( empty( $byline_about['short-bio'] ) ) {
						$byline_about['short-bio'] = '';
					}
					// Add meta data.
					update_post_meta( $byline_id, 'fm_bylines_about', $byline_about );
				}
			}
			WP_CLI::success( 'Migration complete' );
		}
	}

	/**
	 * Migrate Post Authors to Bylines
	 *
	 * @subcommand migrate_posts_author
	 * @synopsis [--post_type] [--batch_size]
	 */
	public function migrate_posts_authors( $args, $assoc_args ) {
		global $coauthors_plus;
		// Particular Post Type to Loop Over.
		if ( ! empty( $assoc_args['post_type'] ) ) {
			$post_type = $assoc_args['post_type'];
		} else {
			$post_type = 'post';
		}

		// Batch size.
		if ( ! empty( $assoc_args['batch_size'] ) ) {
			$batch_size = (int) $assoc_args['batch_size'];
		} else {
			$batch_size = 10;
		}

		// Setup arrays for storing in memory for large jobs.
		$user_byline_map = array();
		$cap_byline_map = array();

		// Get all the posts.
		$offset = 0;
		while ( ! isset( $complete ) ) {
			$posts = get_posts(
				array(
					'posts_per_page' => $batch_size,
					'post_type' => $post_type,
					'offset' => $offset,
				)
			);
			$offset += $batch_size;
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$byline_id = false;

					// Check that the byline is empty, then add it.
					$byline_set = get_post_meta( $post->ID, 'fm_bylines_author', true );

					if ( empty( $byline_set ) ) {
						// Check for a CAP.
						$cap_terms = wp_get_post_terms( $post->ID, $coauthors_plus->coauthor_taxonomy, $args );

						// If CAP exists, get attached Byline.
						if ( ! empty( $cap_terms[0]->term_id ) ) {
							// Look up in memory first.
							if ( ! empty( $cap_byline_map[ $cap_terms[0]->term_id ] ) ) {
								$byline_id = $cap_byline_map[ $cap_terms[0]->term_id ];
							} else {
								$cap_term = get_term_by( 'id' , $cap_terms[0]->term_id, $coauthors_plus->coauthor_taxonomy );

								// Check if there is CAP Post.
								$args = array(
									'post_type' => $coauthors_plus->guest_authors->post_type,
									'name' => $cap_term->slug,
									'post_status' => 'publish',
									'numberposts' => 1,
								);
								$cap_post = get_posts( $args );
								if ( ! empty( $cap_post[0]->ID ) ) {
									$cap_meta = get_post_meta( $cap_post[0]->ID );
								} else {
									$cap_meta = false;
								}

								if ( ! empty( $cap_meta['fm_bylines_id'][0] ) ) {
									$byline_id = (int) $cap_meta['fm_bylines_id'][0];
									// Store in memory.
									$cap_byline_map[ $cap_terms[0]->term_id ] = $byline_id;
								}
							}
						}

						// If CAP doesnt exists, get User and look for attached Byline with that author.
						if ( empty( $byline_id ) ) {
							if ( ! empty( $user_byline_map[ $post->post_author ] ) ) {
								$byline_id = $cap_byline_map[ $post->post_author ];
							} else {
								$args = array(
									'post_type' => FM_Bylines()->name,
									'meta_key' => 'fm_bylines_user_mapping',
									'meta_value' => $post->post_author,
									'post_status' => 'publish',
									'numberposts' => 1,
								);
								$byline_post = get_posts( $args );
								if ( ! empty( $byline_post[0]->ID ) ) {
									$byline_id = $byline_post[0]->ID;
									// Store in memory.
									$cap_byline_map[ $post->post_author ] = $byline_id;
								}
							}
						}

						// Add the byline to this post.
						if ( ! empty( $byline_id ) ) {
							$current_bylines = array();
							$new_byline_entry = array(
								'byline_id' => $byline_id,
								'fm_byline_type' => 'author',
							);
							$current_bylines[] = $new_byline_entry;
							update_post_meta( $post->ID, 'fm_bylines_author', $current_bylines );
							update_post_meta( $post->ID, 'fm_bylines_author_' . $byline_id, count( $current_bylines ) );
						}
					}
					$complete = true;
				}
			} else {
				$complete = true;
			}
		}
	}
}

