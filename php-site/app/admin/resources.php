<?php
/*
 * Content types managed with the generic list/edit screens.
 * Field: key => [label, type, options…]
 *   types: text, slug, textarea, html, select, date, number, check, image, gallery, faq
 *   options: 'required', 'help' => '…', 'options' => [...], 'from' => 'title' (slug source),
 *            'group' => 'seo' (shown in the SEO panel), 'max' => n (counter), 'readonly'
 */
function resources(): array
{
    $seo = [
        'seo_title' => ['SEO title', 'text', 'group' => 'seo', 'max' => 60, 'help' => 'Shown in Google and the browser tab. Leave empty to use the default.'],
        'seo_desc' => ['SEO description', 'textarea', 'group' => 'seo', 'max' => 160, 'help' => 'The grey text under the title in Google. Aim for 120–160 characters.'],
        'focus_keyword' => ['Focus keyword', 'text', 'group' => 'seo', 'max' => 190, 'help' => 'The primary phrase this page should target. Used for the on-page SEO checks.'],
        'canonical_url' => ['Canonical URL', 'text', 'group' => 'seo', 'max' => 500, 'help' => 'Optional. Use a full https:// URL or a site path beginning with /.'],
        'og_title' => ['Social title', 'text', 'group' => 'seo', 'max' => 70, 'help' => 'Optional title for social sharing. Defaults to the SEO title.'],
        'og_desc' => ['Social description', 'textarea', 'group' => 'seo', 'max' => 200, 'help' => 'Optional description for social sharing. Defaults to the SEO description.'],
        'og_image' => ['Social image', 'image', 'group' => 'seo', 'help' => 'Optional image used in social cards.'],
        'noindex' => ['Noindex (hide from search results)', 'check', 'group' => 'seo'],
        'nofollow' => ['Nofollow (do not follow links)', 'check', 'group' => 'seo'],
    ];
    $seoFields = fn(array $options) => $seo + [
        'schema_type' => ['Schema type', 'select', 'options' => $options, 'group' => 'seo'],
    ];
    $schemaOptions = [
        'posts' => ['auto' => 'Automatic (BlogPosting)', 'BlogPosting' => 'Blog posting', 'Article' => 'Article', 'NewsArticle' => 'News article'],
        'projects' => ['auto' => 'Automatic (CreativeWork)', 'CreativeWork' => 'Creative work'],
        'cities' => ['auto' => 'Automatic (Service)', 'Service' => 'Service', 'WebPage' => 'Web page'],
        'pages' => ['auto' => 'Automatic', 'WebPage' => 'Web page', 'AboutPage' => 'About page', 'ContactPage' => 'Contact page', 'Service' => 'Service'],
        'jobs' => ['auto' => 'Automatic (JobPosting)', 'JobPosting' => 'Job posting'],
    ];
    $status = ['Status', 'select', 'options' => ['published' => 'Published', 'draft' => 'Draft (hidden)']];

    return [
        'posts' => [
            'title' => 'Articles', 'one' => 'article', 'table' => 'posts', 'order' => 'published_at DESC, id DESC', 'search' => ['title', 'category', 'slug'],
            'list' => ['title' => 'Title', 'category' => 'Category', 'status' => 'Status', 'published_at' => 'Date'],
            'url' => fn($r) => '/resources/' . $r['slug'] . '/',
            'seo_details' => true, 'seo_source' => 'body',
            'fields' => [
                'title' => ['Title', 'text', 'required', 'max' => 110],
                'slug' => ['URL', 'slug', 'from' => 'title', 'prefix' => '/resources/', 'help' => 'Changing this after publishing breaks old links — add a redirect if you do.'],
                'category' => ['Category', 'text', 'required', 'datalist' => 'SELECT DISTINCT category FROM posts ORDER BY category'],
                'status' => $status,
                'published_at' => ['Publish date', 'date', 'required', 'help' => 'A future date schedules the article: it goes live on that day.'],
                'author' => ['Author', 'text'],
                'excerpt' => ['Summary', 'textarea', 'max' => 200, 'help' => 'Shown on article cards and used for Google when the SEO description is empty.'],
                'cover' => ['Header image', 'image', 'help' => 'Wide image at the top of the article and for social sharing (1600 × 900 works well).'],
                'thumb' => ['Card image', 'image', 'help' => 'Optional smaller image for article cards. The header image is used if empty.'],
                'body' => ['Article', 'html', 'required'],
            ] + $seoFields($schemaOptions['posts']),
            'defaults' => ['status' => 'draft', 'published_at' => date('Y-m-d'), 'author' => 'Morphora Spaces', 'category' => 'Office Design'],
        ],
        'projects' => [
            'title' => 'Projects', 'one' => 'project', 'table' => 'projects', 'order' => 'sort_order, id', 'search' => ['name', 'city'],
            'list' => ['name' => 'Project', 'city' => 'City', 'size' => 'Size', 'status' => 'Status', 'featured' => 'On home'],
            'url' => fn($r) => '/portfolio/' . $r['slug'] . '/',
            'seo_details' => true, 'seo_source' => 'summary',
            'fields' => [
                'name' => ['Client / project name', 'text', 'required'],
                'slug' => ['URL', 'slug', 'from' => 'name', 'prefix' => '/portfolio/'],
                'city' => ['City', 'text', 'required'],
                'size' => ['Area', 'text', 'help' => 'For example “5,000 sq ft”.'],
                'delivery' => ['Delivery', 'text', 'help' => 'For example “Design & Build” or “Managed & executed with …”.'],
                'status' => $status,
                'featured' => ['Show on home page', 'check'],
                'sort_order' => ['Order', 'number', 'help' => 'Lower numbers come first.'],
                'cover' => ['Main photo', 'image', 'required'],
                'summary' => ['About the project', 'html', 'help' => 'Optional write-up shown under the project facts.'],
                'gallery' => ['Gallery', 'gallery', 'help' => 'Upload photos; drag to reorder. Visitors can click to enlarge.'],
            ] + $seoFields($schemaOptions['projects']),
            'defaults' => ['status' => 'published', 'featured' => 1, 'sort_order' => 10],
        ],
        'team' => [
            'title' => 'Team', 'one' => 'team member', 'table' => 'team', 'order' => 'sort_order, id', 'search' => ['name', 'role'],
            'list' => ['name' => 'Name', 'role' => 'Role', 'status' => 'Status', 'sort_order' => 'Order'],
            'url' => fn($r) => '/about/',
            'intro' => 'People shown in the “Our team” section of the About page. Give one person (usually the founder) a message and it appears as the “Founder’s message” section.',
            'fields' => [
                'name' => ['Name', 'text', 'required'],
                'role' => ['Role / title', 'text', 'required', 'help' => 'For example “Founder & Principal Designer”.'],
                'status' => $status,
                'sort_order' => ['Order', 'number', 'help' => 'Lower numbers come first. Put founders first.'],
                'photo' => ['Photo', 'image', 'help' => 'Portrait photo, at least 600 × 750 px, face centred. Without a photo the initials are shown.'],
                'linkedin' => ['LinkedIn profile', 'text', 'help' => 'Optional. Full https:// link.'],
                'bio' => ['Short bio', 'textarea', 'max' => 220, 'help' => 'Optional. One or two lines under the name.'],
                'message' => ['Founder’s message', 'textarea', 'help' => 'Only for the founder. Leave a blank line between paragraphs. If several people have a message, the first one by order is shown.'],
            ],
            'defaults' => ['status' => 'published', 'sort_order' => 10],
        ],
        'testimonials' => [
            'title' => 'Testimonials', 'one' => 'testimonial', 'table' => 'testimonials', 'order' => 'sort_order, id', 'search' => ['name', 'company', 'quote'],
            'list' => ['name' => 'Client', 'company' => 'Company', 'status' => 'Status', 'sort_order' => 'Order'],
            'url' => fn($r) => '/about/',
            'intro' => 'Client quotes shown below the office floor plan on the homepage. Only use real quotes, with the client’s permission.',
            'fields' => [
                'name' => ['Client name', 'text', 'required'],
                'role' => ['Designation', 'text', 'help' => 'For example “Head of Admin”.'],
                'company' => ['Company', 'text'],
                'status' => $status,
                'sort_order' => ['Order', 'number', 'help' => 'Lower numbers come first.'],
                'photo' => ['Photo or company logo', 'image', 'help' => 'Optional. A small square image. Without one the initials are shown.'],
                'quote' => ['Quote', 'textarea', 'required', 'max' => 400, 'help' => 'Their words, kept short. Leave a blank line between paragraphs.'],
            ],
            'defaults' => ['status' => 'published', 'sort_order' => 10],
        ],
        'cities' => [
            'title' => 'Cities', 'one' => 'city', 'table' => 'cities', 'order' => 'top DESC, name', 'search' => ['name', 'slug'],
            'list' => ['name' => 'City', 'status' => 'Status', 'top' => 'Top city', 'updated_at' => 'Updated'],
            'url' => fn($r) => '/office-interior-designer-in-' . $r['slug'] . '/',
            'seo_details' => true, 'seo_source' => 'body',
            'fields' => [
                'name' => ['City name', 'text', 'required'],
                'slug' => ['URL', 'slug', 'from' => 'name', 'prefix' => '/office-interior-designer-in-'],
                'status' => $status,
                'top' => ['Top city (highlighted on the Locations page)', 'check'],
                'lead' => ['Intro line', 'textarea', 'help' => 'Optional. Replaces the standard “Looking for an office interior designer in …” intro.'],
                'hero' => ['Header photo', 'image', 'help' => 'Optional. A photo from a project in or near this city.'],
                'body' => ['Local content', 'html', 'help' => 'Optional but good for Google: projects, areas and business districts you serve in this city. Shown as an extra section.'],
            ] + $seoFields($schemaOptions['cities']),
            'defaults' => ['status' => 'published'],
        ],
        'pages' => [
            'title' => 'Pages', 'one' => 'page', 'table' => 'pages', 'order' => 'template = "page", slug', 'search' => ['slug', 'seo_title'], 'nocreate' => true, 'nodelete' => true,
            'list' => ['slug' => 'Page', 'template' => 'Type', 'seo_title' => 'SEO title', 'updated_at' => 'Updated'],
            'url' => fn($r) => path_for($r['slug']),
            'seo_details' => true, 'seo_source' => 'body',
            'fields' => [
                'slug' => ['URL', 'slug', 'readonly', 'prefix' => '/'],
                'body' => ['Page content', 'html', 'help' => 'Live sections appear as <!--sc:…--> lines: keep them as they are, or move them to reorder sections.', 'only' => ['template' => 'page']],
                'foot_label' => ['Contact section label', 'text', 'help' => 'Small label above the contact section at the bottom of this page.'],
                'foot_title' => ['Contact section heading', 'text', 'help' => 'HTML allowed, e.g. Tell us about <em>your office.</em>'],
            ] + $seoFields($schemaOptions['pages']),
        ],
        'jobs' => [
            'title' => 'Jobs', 'one' => 'job', 'table' => 'jobs', 'order' => 'status = "published" DESC, created_at DESC', 'search' => ['title', 'location'],
            'list' => ['title' => 'Role', 'location' => 'Location', 'status' => 'Status', 'created_at' => 'Posted'],
            'url' => fn($r) => '/careers/' . $r['slug'] . '/',
            'seo_details' => true, 'seo_source' => 'description',
            'fields' => [
                'title' => ['Job title', 'text', 'required'],
                'slug' => ['URL', 'slug', 'from' => 'title', 'prefix' => '/careers/'],
                'status' => ['Status', 'select', 'options' => ['published' => 'Open (published)', 'draft' => 'Draft (hidden)', 'closed' => 'Closed']],
                'location' => ['Location', 'text', 'required'],
                'type' => ['Type', 'select', 'options' => ['Full-time' => 'Full-time', 'Part-time' => 'Part-time', 'Contract' => 'Contract', 'Internship' => 'Internship']],
                'experience' => ['Experience', 'text', 'help' => 'For example “2–5 years”.'],
                'valid_through' => ['Apply by', 'date', 'help' => 'Optional. The job closes automatically after this date.'],
                'description' => ['Job description', 'html', 'required'],
            ] + $seoFields($schemaOptions['jobs']),
            'defaults' => ['status' => 'draft', 'type' => 'Full-time', 'location' => 'Thane, Maharashtra'],
        ],
        'redirects' => [
            'title' => 'Redirects', 'one' => 'redirect', 'table' => 'redirects', 'order' => 'hits DESC, id DESC', 'search' => ['from_path', 'to_path'],
            'list' => ['from_path' => 'From', 'to_path' => 'To', 'code' => 'Type', 'hits' => 'Hits'],
            'intro' => 'Send old or changed URLs to the right page, so Google rankings and links keep working. The old WordPress URLs are already here.',
            'fields' => [
                'from_path' => ['Old URL path', 'text', 'required', 'help' => 'Path only, e.g. /old-page/'],
                'to_path' => ['New URL', 'text', 'required', 'help' => 'Path like /resources/new-page/ or a full https:// address.'],
                'code' => ['Type', 'select', 'options' => ['301' => 'Permanent (301) — normal choice', '302' => 'Temporary (302)']],
            ],
            'defaults' => ['code' => 301],
        ],
    ];
}
