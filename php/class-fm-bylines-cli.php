<?php
WP_CLI::add_command( 'fm-bylines', 'FM_Bylines_CLI' );

class FM_Bylines_CLI extends WP_CLI_Command {

	/**
	 * Migrate Co-Authors Plus Guest Authors and linked accounts to FM Bylines
	 *
	 * @subcommand migrate_coauthors
	 * @synopsis [--cleardata]
	 */
	public function migrate_coauthors( $args, $assoc_args ) {
		global $coauthors_plus;
		// Clear the legacy CAP data
		if ( $assoc_args['cleardata'] ) {
			$clear_data = true;
		} else {
			$clear_data = false;
		}

		if ( ! empty( $coauthors_plus->coauthor_taxonomy ) ) {

			$cap_terms = get_terms( $coauthors_plus->coauthor_taxonomy, array( 'hide_empty' => false ) );

			if ( ! is_wp_error( $cap_terms ) && ! empty( $cap_terms ) ) {

				WP_CLI::line( 'Migrating Co-Authors Plus guest-authors to bylines:' );

				foreach ( $cap_terms as $cap_term ) {
					$args = array(
						'post_type' => $coauthors_plus->guest_authors->post_type,
						'name' => $cap_term->slug,
						'post_status' => 'publish',
						'numberposts' => 1,
					);
					$cap_post = get_posts( $args );
					if ( ! empty( $cap_post[0]->ID ) ) {
						$cap_meta = apply_filters( 'fm_bylines_cli_cap_meta', get_post_meta( $cap_post[0]->ID ), $cap_post[0], $cap_term );

						// Check to see if a byline exists for this CAP already
						$byline_slug = preg_replace( '/^cap-/', '', $cap_term->slug );
						$args = array(
							'post_type' => FM_Bylines()->name,
							'name' => $byline_slug,
							'post_status' => 'publish',
							'numberposts' => 1,
						);
						$byline_post = get_posts( $args );

						if ( empty( $byline_post[0]->ID ) ) {
							// Create a byline post
							$byline_args = array(
								'post_title' => $cap_meta['cap-display_name'][0],
								'post_name' => $byline_slug,
								'post_type' => FM_Bylines()->name,
								'post_status' => 'publish',
							);
							$byline_id = wp_insert_post( $byline_args );
							if ( ! empty( $byline_id ) ) {
								// Set byline metadata to CAP metadata
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

								if ( ! empty( $cap_meta['cap-linked_account'][0] ) ) {
									$mapped_user = get_user_by( 'slug', $cap_meta['cap-linked_account'][0] );
									if ( ! empty( $mapped_user ) ) {
										update_post_meta( $byline_id, 'fm_bylines_user_mapping', $mapped_user->ID );
									}
								}
							} else {
								WP_CLI::line( "Falied to insert byline: {$byline_slug}" );
							}
						} else {
							$byline_id = $byline_post[0]->ID;
						}

						do_action( 'fm_bylines_cli_post_cap_migration', $byline_id, $cap_meta, $cap_term, $cap_post[0] );

						// Migrate the legacy CAP metadata and store in case custom meta fields were missed.

						// Clean legacy data. Don't delete by default. This will migrate the featured image as well.
						if ( $clear_data ) {
							//We are just flipping the post id here.
							global $wpdb;
							$wpdb->update( $wpdb->postmeta, array( 'post_id' => $byline_id ), array( 'post_id' => $cap_post[0]->ID ), array( '%d' ), array( '%d' ) );
						} else {
							// Copy cap meta
							foreach ( $cap_meta as $key => $value ) {
								update_post_meta( $byline_id, $key, $value[0] );
							}
						}

						// Update posts so byline authors reflect CAP authors
						$objects = get_objects_in_term( $cap_term->term_id, $coauthors_plus->coauthor_taxonomy );
						foreach ( $objects as $object_id ) {
							if ( get_post_type( $object_id ) !== $coauthors_plus->guest_authors->post_type ) {
								$current_bylines = empty( get_post_meta( $byline_id, 'fm_bylines_author', true ) ) ? array() : get_post_meta( $byline_id, 'fm_bylines_author', true );
								$new_byline_entry = array(
									'byline_id' => $byline_id,
									'fm_byline_type' => $coauthors_plus->coauthor_taxonomy,
								);
								$current_bylines[] = $new_byline_entry;
								update_post_meta( $byline_id, 'fm_bylines_' . $coauthors_plus->coauthor_taxonomy, $current_bylines );
								update_post_meta( $byline_id, 'fm_bylines_' . $coauthors_plus->coauthor_taxonomy . '_' . $byline_id, count( $current_bylines ) );
							}

							// Clean legacy data. Don't delete by default.
							if ( $clear_data ) {
								wp_delete_object_term_relationships( $object_id, $coauthors_plus->coauthor_taxonomy );
							}
						}

						// Clean legacy data. Don't delete by default.
						if ( $clear_data ) {
							// Delete CAP term and associated objects
							wp_delete_term( $cap_term->term_id, $coauthors_plus->coauthor_taxonomy );

							// Delete CAP Post
							wp_delete_post( $cap_post[0]->ID, true );
						}

						WP_CLI::line( "Migrated {$cap_term->slug}" );
					} else {
						WP_CLI::line( "Skipping {$cap_term->slug} migration as it has no metadata" );
						if ( $clear_data ) {
							wp_delete_term( $cap_term->term_id, $coauthors_plus->coauthor_taxonomy );
							WP_CLI::line( "Deleted {$cap_term->slug} term" );
						}
					}
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
	 */
	public function migrate_wp_users( $args, $assoc_args ) {

		$wp_users = get_users( 'number=1000' );
		if ( ! is_wp_error( $wp_users ) && ! empty( $wp_users ) ) {

			WP_CLI::line( 'Migrating WP users to bylines:' );

			foreach ( $wp_users as $user ) {
				$user_data = $user->data;
				echo 'Migrating user: ' . $user->data->display_name . "\n";

				// Check to see that the user has published posts.
				$args = array(
					'author' => $user_data->ID,
				);
				$user_posts = get_posts( $args );
				if ( ! empty( $user_posts[0]->ID ) ) {
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
						$byline_slug = sanitize_title( $user_data->display_name );
						$args = array(
							'post_type' => FM_Bylines()->name,
							'name' => $byline_slug,
							'post_status' => 'publish',
							'numberposts' => 1,
						);
						$byline_post = get_posts( $args );

						if ( empty( $byline_post[0]->ID ) ) {
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

								// Add meta data.
								update_post_meta( $byline_id, 'fm_bylines_contact_info', array(
									'email' => $user_data->user_email,
									'website' => ( ! empty( $user_data->user_url ) && $user_data->user_url != 'http://' ) ? $user_data->user_url : '',
									'twitter' => '',
									'linkedin' => '',
								) );

								$bio = get_the_author_meta( 'description', $user_data->ID );
								update_post_meta( $byline_id, 'fm_bylines_about', array(
									'bio' => ! empty( $bio ) ? $bio : '',
									'short-bio' => '',
								) );
							}
						} else {
							$byline_id = $byline_post[0]->ID;
						}
						if ( ! empty( $byline_id ) ) {
							// Loop through all the posts from that user and apply the byline.
							$offset = 0;
							while ( ! isset( $complete ) ) {
								$posts = get_posts(
									array(
										'posts_per_page'   => 10,
										'offset' => $offset,
										'author' => $user_data->ID,
									)
								);
								$offset += 10;
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
						}
					}
				}
			}
			WP_CLI::success( 'Migration complete' );
		}
	}



	/**
	 * Migrate Post Authors to Bylines
	 *
	 * @subcommand migrate_post_authors
	 */
	public function migrate_post_authors( $args, $assoc_args ) {
		global $coauthors_plus;
		// Particular Post Type to Loop Over
		if ( ! empty( $assoc_args['post_type'] ) ) {
			$post_type = $assoc_args['post_type'];
		} else {
			$post_type = 'post';
		}

		// Get all the posts
		$offset = 0;
		while ( ! isset( $complete ) ) {
			$posts = get_posts(
				array(
					'posts_per_page' => 10,
					'post_type' => $post_type,
					'offset' => $offset,
					'include' => array( '168077' ),
					'numberposts' => 1,
				)
			);
			print_r( $posts );
			$offset += 10;
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					// Check that the byline is empty, then add it.
					$byline_set = get_post_meta( $post->ID, 'fm_bylines_author', true );
					print_r( $byline_set );
					if ( empty( $byline_set ) ) {
						// Check for a CAP.
						$terms = wp_get_post_terms( $post->ID, $coauthors_plus->coauthor_taxonomy, $args );
						print_r( $terms );
						// If CAP exists, get attached Byline.

						// If CAP doesnt exists, get User and look for attached Byline.


						// Add the byline to this post.
						// $new_byline_entry = array(
						// 	'byline_id' => $byline_id,
						// 	'fm_byline_type' => 'author',
						// );
						// $current_bylines[] = $new_byline_entry;
						// update_post_meta( $post->ID, 'fm_bylines_author', $current_bylines );
						// update_post_meta( $post->ID, 'fm_bylines_author_' . $byline_id, count( $current_bylines ) );
					}
					$complete = true;
				}
			} else {
				$complete = true;
			}
		}
	}
}

