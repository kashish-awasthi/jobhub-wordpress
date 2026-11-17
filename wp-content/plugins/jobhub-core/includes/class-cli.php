<?php
namespace JobHub;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands.
 *
 *   wp jobhub seed [--force]   Load demo employers, jobs, pages and applications.
 *   wp jobhub expire           Run the expiry task now.
 */
class CLI {

	/**
	 * Loads demo data.
	 *
	 * [--force]
	 * : Seed again even if demo data already exists.
	 */
	public function seed( $args, $assoc ): void {
		if ( get_option( 'jobhub_seeded' ) && empty( $assoc['force'] ) ) {
			\WP_CLI::success( 'Demo data already loaded (use --force to add again).' );
			return;
		}

		update_option( 'blogname', 'JobHub' );
		update_option( 'blogdescription', 'Find work you love' );

		$terms = [
			'job_category' => [ 'Engineering', 'Design', 'Marketing', 'Data & AI', 'Sales', 'Customer Support' ],
			'job_type'     => [ 'Full-time', 'Part-time', 'Contract', 'Internship' ],
			'job_location' => [ 'London', 'Berlin', 'New York', 'Bangalore', 'Toronto', 'Amsterdam' ],
		];
		foreach ( $terms as $tax => $names ) {
			foreach ( $names as $name ) {
				if ( ! term_exists( $name, $tax ) ) {
					wp_insert_term( $name, $tax );
				}
			}
		}

		$employer = $this->user( 'employer', 'employer123', 'employer@jobhub.test', 'Northwind Labs' );
		$second   = $this->user( 'brightpath', wp_generate_password( 16 ), 'hiring@brightpath.test', 'BrightPath Health' );

		$submit    = $this->page( 'Post a Job', '<!-- wp:shortcode -->[jobhub_submit_job]<!-- /wp:shortcode -->' );
		$dashboard = $this->page( 'Employer Dashboard', '<!-- wp:shortcode -->[jobhub_dashboard]<!-- /wp:shortcode -->' );
		$this->page( 'About', "<!-- wp:paragraph -->\n<p>JobHub connects great people with teams that are hiring. Employers post roles in minutes and review applicants in one simple dashboard.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Latest featured roles</h2>\n<!-- /wp:heading -->\n\n<!-- wp:jobhub/latest-jobs {\"count\":3,\"featuredOnly\":true} /-->" );

		$settings                   = get_option( Settings::OPTION, [] );
		$settings['submit_page']    = $submit;
		$settings['dashboard_page'] = $dashboard;
		$settings += [ 'per_page' => 8, 'currency' => '$', 'max_cv_mb' => 2, 'expire_days' => 60 ];
		update_option( Settings::OPTION, $settings );

		$created = [];
		foreach ( $this->jobs() as $i => $job ) {
			$author = in_array( $job['company'], [ 'Northwind Labs', 'BrightPath Health' ], true )
				? ( 'Northwind Labs' === $job['company'] ? $employer : $second )
				: ( $i % 2 ? $employer : $second );

			$id = wp_insert_post( [
				'post_type'    => 'job',
				'post_status'  => 'publish',
				'post_title'   => $job['title'],
				'post_content' => $this->description( $job ),
				'post_excerpt' => $job['summary'],
				'post_author'  => $author,
				'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( $i * 9 + 2 ) * HOUR_IN_SECONDS ),
			] );

			wp_set_object_terms( $id, $job['category'], 'job_category' );
			wp_set_object_terms( $id, $job['type'], 'job_type' );
			wp_set_object_terms( $id, $job['location'], 'job_location' );

			$meta = [
				'company'     => $job['company'],
				'company_url' => 'https://example.com',
				'salary_min'  => $job['salary'][0],
				'salary_max'  => $job['salary'][1],
				'remote'      => $job['remote'],
				'featured'    => $job['featured'] ?? false,
				'deadline'    => gmdate( 'Y-m-d', strtotime( '+' . ( 20 + $i * 3 ) . ' days' ) ),
				'apply_email' => get_userdata( $author )->user_email,
				'filled'      => false,
			];
			foreach ( $meta as $k => $v ) {
				update_post_meta( $id, '_jobhub_' . $k, $v );
			}
			$created[] = $id;
		}

		$this->applications( array_slice( $created, 0, 3 ) );
		$this->menu( $submit, $dashboard );

		update_option( 'jobhub_seeded', 1 );
		delete_transient( 'jobhub_stats' );
		\WP_CLI::success( sprintf( 'Created %d jobs, 2 employers and demo applications.', count( $created ) ) );
	}

	/** Runs the expiry task now. */
	public function expire(): void {
		\WP_CLI::success( sprintf( 'Expired %d jobs.', Expiry::run() ) );
	}

	private function user( string $login, string $pass, string $email, string $company ): int {
		$existing = get_user_by( 'login', $login );
		if ( $existing ) {
			return $existing->ID;
		}
		$id = wp_insert_user( [
			'user_login'   => $login,
			'user_pass'    => $pass,
			'user_email'   => $email,
			'display_name' => $company,
			'role'         => 'employer',
		] );
		update_user_meta( $id, 'jobhub_company', $company );
		return (int) $id;
	}

	private function page( string $title, string $content ): int {
		$existing = get_page_by_path( sanitize_title( $title ) );
		if ( $existing ) {
			return $existing->ID;
		}
		return (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ] );
	}

	private function menu( int $submit, int $dashboard ): void {
		$name = 'Primary';
		$menu = wp_get_nav_menu_object( $name );
		if ( $menu ) {
			return;
		}
		$menu_id = wp_create_nav_menu( $name );
		wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-title' => 'Find Jobs', 'menu-item-url' => get_post_type_archive_link( 'job' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ] );
		wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-title' => 'About', 'menu-item-object' => 'page', 'menu-item-object-id' => get_page_by_path( 'about' )->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ] );
		wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-title' => 'For Employers', 'menu-item-object' => 'page', 'menu-item-object-id' => $dashboard, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ] );

		$locations            = get_theme_mod( 'nav_menu_locations', [] );
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	private function applications( array $job_ids ): void {
		$dir = Applications::cv_dir();
		// A tiny valid PDF so the "Download CV" button works in the demo.
		$pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj 4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj 5 0 obj<</Length 44>>stream\nBT /F1 24 Tf 72 760 Td (Demo CV) Tj ET\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

		$people = [
			[ 'Priya Sharma', 'priya@example.com', 'I have 4 years of experience building WordPress and React applications, and I love shipping features that customers notice.' ],
			[ 'Lucas Meyer', 'lucas@example.com', 'Product-minded engineer with a background in fintech. I enjoy clean APIs, good tests and mentoring junior developers.' ],
			[ 'Amara Okafor', 'amara@example.com', 'I recently led a redesign that improved conversion by 18%. I would love to bring that same focus on users to your team.' ],
		];

		foreach ( $job_ids as $n => $job_id ) {
			foreach ( array_slice( $people, 0, 3 - $n ) as $k => [ $name, $email, $letter ] ) {
				$file = wp_generate_password( 32, false ) . '.pdf';
				file_put_contents( trailingslashit( $dir ) . $file, $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				wp_insert_post( [
					'post_type'    => 'job_application',
					'post_status'  => 'publish',
					'post_title'   => $name . ' — ' . get_the_title( $job_id ),
					'post_content' => $letter,
					'meta_input'   => [
						'_jobhub_job_id'  => $job_id,
						'_jobhub_name'    => $name,
						'_jobhub_email'   => $email,
						'_jobhub_phone'   => '',
						'_jobhub_cv_file' => $file,
						'_jobhub_cv_name' => sanitize_title( $name ) . '-cv.pdf',
						'_jobhub_status'  => [ 'new', 'shortlisted', 'reviewed' ][ $k ],
					],
				] );
			}
		}
	}

	private function description( array $job ): string {
		$items = static fn( array $list ) => '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $list ) ) . '</li></ul>';
		return sprintf(
			"<p>%s</p>\n<h3>What you'll do</h3>\n%s\n<h3>What we're looking for</h3>\n%s\n<h3>Benefits</h3>\n%s",
			esc_html( $job['summary'] ),
			$items( $job['do'] ),
			$items( $job['need'] ),
			$items( [ 'Flexible hours and a hybrid or remote setup', '25 days paid holiday', 'Yearly learning budget', 'Private health cover' ] )
		);
	}

	private function jobs(): array {
		return [
			[ 'title' => 'Senior WordPress Developer', 'company' => 'Northwind Labs', 'category' => 'Engineering', 'type' => 'Full-time', 'location' => 'London', 'salary' => [ 70000, 90000 ], 'remote' => true, 'featured' => true,
				'summary' => 'Build fast, accessible WordPress sites and custom plugins for our agency clients.',
				'do' => [ 'Build custom themes and Gutenberg blocks', 'Write custom plugins with clean, tested PHP', 'Improve Core Web Vitals across client sites' ],
				'need' => [ '4+ years of PHP and WordPress', 'Experience with the REST API and WP-CLI', 'Comfortable with Git and code review' ] ],
			[ 'title' => 'Product Designer', 'company' => 'BrightPath Health', 'category' => 'Design', 'type' => 'Full-time', 'location' => 'Berlin', 'salary' => [ 60000, 78000 ], 'remote' => false, 'featured' => true,
				'summary' => 'Design patient-friendly experiences for our telehealth app used by 2M people.',
				'do' => [ 'Own end-to-end design for key features', 'Run user interviews and usability tests', 'Grow our design system' ],
				'need' => [ 'Strong portfolio of shipped products', 'Figma expertise', 'Empathy for non-technical users' ] ],
			[ 'title' => 'Frontend Engineer (React)', 'company' => 'Lumen Pay', 'category' => 'Engineering', 'type' => 'Full-time', 'location' => 'Amsterdam', 'salary' => [ 65000, 85000 ], 'remote' => true,
				'summary' => 'Help us build a payments dashboard that thousands of small businesses rely on.',
				'do' => [ 'Ship React and TypeScript features', 'Work closely with design on UI polish', 'Improve performance and accessibility' ],
				'need' => [ '3+ years with React', 'Solid CSS skills', 'Testing with Jest or Playwright' ] ],
			[ 'title' => 'Data Analyst', 'company' => 'Orbit Retail', 'category' => 'Data & AI', 'type' => 'Full-time', 'location' => 'New York', 'salary' => [ 75000, 95000 ], 'remote' => false,
				'summary' => 'Turn sales and customer data into decisions that shape our 120 stores.',
				'do' => [ 'Build dashboards for leadership', 'Write SQL to answer business questions', 'Partner with marketing on experiments' ],
				'need' => [ 'Strong SQL', 'Experience with Looker or Power BI', 'Clear communication' ] ],
			[ 'title' => 'Content Marketing Manager', 'company' => 'Northwind Labs', 'category' => 'Marketing', 'type' => 'Full-time', 'location' => 'London', 'salary' => [ 50000, 62000 ], 'remote' => true,
				'summary' => 'Lead our blog, newsletter and case studies to grow inbound leads.',
				'do' => [ 'Plan and write content each week', 'Own SEO for the marketing site', 'Report on traffic and conversions' ],
				'need' => [ 'Excellent writing', 'WordPress and SEO experience', 'Data-driven mindset' ] ],
			[ 'title' => 'Machine Learning Engineer', 'company' => 'Cobalt AI', 'category' => 'Data & AI', 'type' => 'Full-time', 'location' => 'Toronto', 'salary' => [ 110000, 140000 ], 'remote' => true, 'featured' => true,
				'summary' => 'Train and deploy models that help logistics firms predict demand.',
				'do' => [ 'Build training and evaluation pipelines', 'Deploy models to production', 'Monitor model quality over time' ],
				'need' => [ 'Python and PyTorch', 'Experience with cloud ML tooling', 'Strong maths foundations' ] ],
			[ 'title' => 'Customer Success Specialist', 'company' => 'BrightPath Health', 'category' => 'Customer Support', 'type' => 'Part-time', 'location' => 'Berlin', 'salary' => [ 28000, 34000 ], 'remote' => true,
				'summary' => 'Help clinics get the most from BrightPath through onboarding and support.',
				'do' => [ 'Onboard new clinic customers', 'Answer support tickets', 'Share feedback with the product team' ],
				'need' => [ 'Friendly and patient', 'Fluent English and German', 'Experience with Zendesk is a plus' ] ],
			[ 'title' => 'Backend Engineer (PHP / Laravel)', 'company' => 'Lumen Pay', 'category' => 'Engineering', 'type' => 'Contract', 'location' => 'Amsterdam', 'salary' => [ 80000, 100000 ], 'remote' => true,
				'summary' => '6-month contract to scale our invoicing API.',
				'do' => [ 'Design and build REST APIs', 'Optimise MySQL queries', 'Write automated tests' ],
				'need' => [ 'Strong modern PHP', 'Laravel or Symfony', 'API design experience' ] ],
			[ 'title' => 'Sales Development Rep', 'company' => 'Orbit Retail', 'category' => 'Sales', 'type' => 'Full-time', 'location' => 'New York', 'salary' => [ 55000, 70000 ], 'remote' => false,
				'summary' => 'Start conversations with retail brands and book meetings for our account team.',
				'do' => [ 'Prospect and qualify leads', 'Run outbound campaigns', 'Keep our CRM tidy' ],
				'need' => [ 'Great communication', 'Self-motivated', '1 year in sales is a plus' ] ],
			[ 'title' => 'UX Research Intern', 'company' => 'Cobalt AI', 'category' => 'Design', 'type' => 'Internship', 'location' => 'Toronto', 'salary' => [ 30000, 36000 ], 'remote' => false,
				'summary' => 'Join our design team for 6 months and learn research from the ground up.',
				'do' => [ 'Help plan user interviews', 'Synthesise findings', 'Present insights to the team' ],
				'need' => [ 'Studying design, HCI or psychology', 'Curious about people', 'Good note taking' ] ],
			[ 'title' => 'DevOps Engineer', 'company' => 'Northwind Labs', 'category' => 'Engineering', 'type' => 'Full-time', 'location' => 'Bangalore', 'salary' => [ 45000, 60000 ], 'remote' => true,
				'summary' => 'Own our hosting, CI/CD and monitoring for 200+ WordPress sites.',
				'do' => [ 'Manage Docker-based infrastructure', 'Automate deployments with GitHub Actions', 'Improve uptime and backups' ],
				'need' => [ 'Linux, Docker and Nginx', 'AWS or DigitalOcean', 'Scripting with Bash or Python' ] ],
			[ 'title' => 'Performance Marketing Lead', 'company' => 'Lumen Pay', 'category' => 'Marketing', 'type' => 'Full-time', 'location' => 'London', 'salary' => [ 68000, 82000 ], 'remote' => false,
				'summary' => 'Scale paid acquisition across Google, Meta and LinkedIn.',
				'do' => [ 'Own a six-figure monthly budget', 'Run creative and landing page tests', 'Report on CAC and payback' ],
				'need' => [ '4+ years in paid media', 'Strong analytics skills', 'B2B SaaS experience' ] ],
		];
	}
}

\WP_CLI::add_command( 'jobhub', CLI::class );
