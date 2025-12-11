const processArgs = process.argv.slice(2);
import backstop from 'backstopjs';

const TYPE = {
  FULL: 'full',
  FAST: 'fast',
};

const COMMAND = {
  REFERENCE: 'reference',
  TEST: 'test',
  APPROVE: 'approve',
};

const command = ((argv) => {
  for (const command of Object.values(COMMAND)) {
    if (argv.includes(command)) {
      return command;
    }
  }

  throw new Error('Missing a known command');
})(processArgs);

function getConfig(hostname, protocol, type) {
  const removeDefault = [
    '.header',
    '.breadcrumb__container',
    '.block--react-and-share',
    '.footer',
    '.hds-cc--banner',
    'iframe',
    'time',
  ];

  const viewports = type === TYPE.FAST ? [
    // For faster checks, check only mobile and generic desktop sizes
    {
      label: 'Mobile',
      width: 320,
      height: 450,
    },
    {
      label: 'Desktop',
      width: 1900,
      height: 970,
    },
  ] : [
    // All of our breakpoints
    {
      label: 'Breakpoint_XS',
      width: 320,
      height: 450,
    },
    {
      label: 'Breakpoint_S',
      width: 576,
      height: 630,
    },
    {
      label: 'Breakpoint_M',
      width: 768,
      height: 920,
    },
    {
      label: 'Breakpoint_L',
      width: 992,
      height: 650,
    },
    {
      label: 'Breakpoint_XL',
      width: 1024,
      height: 580,
    },
    {
      label: 'Breakpoint_XXL',
      width: 2560,
      height: 1440,
    },
  ];

  const expandComponents = type !== type.FAST; // Get all the components on page

  const scenarios = [
    {
      label: 'Landing page - hero',
      url: `/fi/etusivu`,
      selectors: ['#block-hdbt-subtheme-heroblock'],
      expect: 1,
    },
    {
      label: 'Notices',
      url: `/fi/kuulutukset-ja-ilmoitukset`,
      selectors: ['.component--all-articles-block'],
      expect: 1,
    },
    {
      label: 'Decisionmakers',
      url: `/fi/paattajat`,
      // This page is extremely slow
      delay: 100,
    },
    {
      label: 'Decisionmakers - city council',
      url: `/fi/paattajat/kaupunginvaltuusto`,
      selectors: ['main'],
      // This page is extremely slow
      delay: 100,
    },
    {
      label: 'Decisionmakers - decisions',
      url: `/fi/paattajat/kaupunginvaltuusto/asiakirjat`,
    },
    {
      label: 'Decisionmakers  - decisions - meeting agenda',
      url: `/fi/paattajat/kaupunginvaltuusto/asiakirjat/02900202514`,
    },
    {
      label: 'Decision - without case number',
      url: `/fi/asia/731e1d08-2d89-405f-94e4-fdb32692b55b`,
    },
    {
      label: 'Decision - full',
      url: `/fi/asia/hel-2023-013016/c7b5c756-84b3-4519-bb5f-3cd558f06225`,
    },
    {
      label: 'Decision - confidential',
      url: `/fi/asia/hel-2024-014058/147213c7-b063-40fc-a319-c91d5b846623`,
    },
  ];

  return {
    filter: processArgs[2] ?? null, // Add filter for label string here if you want to debug a single component, like the events component.
    docker: true,
    config: {
      id: type,
      viewports: viewports,
      dockerCommandTemplate:
        'docker run --rm --network=stonehenge-network -i --user $(id -u):$(id -g) --mount type=bind,source="{cwd}",target=/src backstopjs/backstopjs:{version} {backstopCommand} {args}',
      // Common features are tested in HDBT repo.
      scenarios: scenarios.map(scenario => ({
        // Default values.
        delay: 10,
        removeSelectors: removeDefault,
        // Scenario options.
        ...scenario,
        // Full url.
        url: `${protocol}://${hostname}${scenario.url}`,
      })),
      // mergeImgHack: true,
      onBeforeScript: 'onBefore.js',
      paths: {
        bitmaps_reference: `backstop/${type}/bitmaps_reference`,
        bitmaps_test: `backstop/${type}/bitmaps_test`,
        engine_scripts: 'backstop/',
        html_report: `backstop/${type}/html_report`,
        ci_report: `backstop/${type}/ci_report`,
      },
      report: ['browser'],
      engine: 'playwright',
      engineOptions: {
        browser: 'chromium',
      },
      asyncCaptureLimit: 5,
      asyncCompareLimit: 100,
      debug: false,
      debugWindow: false,
      hostname: `${hostname}`,
    },
  };
}

if (!process.env.DRUPAL_HOSTNAME || !process.env.COMPOSE_PROJECT_NAME) {
  process.exitCode = 1;
  console.error(
    `📕 Environment not found, are you sure the instance .env file is included`,
  );
}

const type = processArgs.includes(TYPE.FAST) ? TYPE.FAST : TYPE.FULL;
const reportUrl = `https://${process.env.DRUPAL_HOSTNAME}/themes/custom/hdbt_subtheme/backstop/${type}/html_report/index.html`;

try {
  await backstop(command, getConfig(`${process.env.COMPOSE_PROJECT_NAME}:8080`, 'http', type))
  console.log(`The ${command} command was successful! Check the report here: ${reportUrl}`);
}
catch (e) {
  process.exitCode = 255;
  console.error('\n\n📕 ', e, `\n\nCheck the report:\n🖼️  ${reportUrl}`);
}
