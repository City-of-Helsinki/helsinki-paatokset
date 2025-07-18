const path = require('path');
const glob = require('glob');

const FriendlyErrorsWebpackPlugin = require('@nuxt/friendly-errors-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');
const { merge } = require('webpack-merge');

// Handle entry points.
const Entries = () => {
  let entries = {
    styles: ['./src/scss/styles.scss'],
    'allu-decisions-search': ['./src/js/react/apps/allu-decisions-search/index.tsx'],
    // Special handling for some javascript or scss.
    // 'some-component': [
    //   './src/scss/some-component.scss',
    //   './src/js/some-component.js',
    // ],
  };

  const pattern = './src/js/**/*.js';
  const ignore = [
    // Some javascript what is needed to ignore and handled separately.
    // './src/js/some-component.js'
  ];

  glob.sync(pattern, {ignore: ignore}).map((item) => {
    entries[path.parse(item).name] = `./${item}` }
  );
  return entries;
};


module.exports = (env, argv) => {

  const isDev = (argv.mode === 'development');

  // Set the base config
  const config = {
    entry() {
      return Entries();
    },
    output: {
      path: path.resolve(__dirname, 'dist'),
      chunkFilename: 'js/async/[name].chunk.js',
      pathinfo: isDev,
      filename: 'js/[name].min.js',
      publicPath: '../',
      clean: true,
    },
    module: {
      rules: [
        {
          test: /\.svg$/,
          include: [
            path.resolve(__dirname, 'src/icons')
          ],
          type: 'asset/resource',
        },
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: ['babel-loader'],
          type: 'javascript/auto',
        },
        {
          test: /\.jsx$/,
          exclude: /node_modules/,
          use: ['babel-loader'],
        },
        {
          test: /\.tsx?$/,
          exclude: /node_modules/,
          use: ['ts-loader'],
        },
        {
          test: /\.(css|sass|scss)$/,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
            },
            {
              loader: 'css-loader',
              options: {
                sourceMap: isDev,
                importLoaders: 2,
                esModule: false,
              },
            },
            {
              loader: 'postcss-loader',
              options: {
                'postcssOptions': {
                  'config': path.join(__dirname, 'postcss.config.js'),
                },
                sourceMap: isDev,
              },
            },
            {
              loader: 'sass-loader',
              options: {
                sourceMap: isDev,
                additionalData: "$debug_mode: " + isDev + ";",
                sassOptions: {
                  quietDeps: true,
                  silenceDeprecations: ['import','mixed-decls'],
                },
              },
            },
          ],
          type: 'javascript/auto',
        },
      ],
    },
    resolve: {
      modules: [path.join(__dirname, 'node_modules')],
      extensions: ['.js', '.jsx', '.ts', '.tsx', '.json'],
      alias: {
        '@/react/common': path.resolve(__dirname, '../../contrib/hdbt/src/js/react/common/'),
        '@/types/': path.resolve(__dirname, '../../contrib/hdbt/src/js/types/'),
      },
    },
    plugins: [
      new FriendlyErrorsWebpackPlugin(),
      new RemoveEmptyScriptsPlugin(),
      new MiniCssExtractPlugin({
        filename: 'css/[name].min.css',
      })
    ],
    watchOptions: {
      aggregateTimeout: 300,
      ignored: ['**/node_modules','**/templates','**/translations/','**/modules', '**/dist/','**/config'],
    },
    // Tell us only about the errors.
    stats: 'errors-only',
    // Suppress performance errors.
    performance: {
      hints: false,
      maxEntrypointSize: 512000,
      maxAssetSize: 512000
    }
  };

  if (argv.mode === 'production') {
    const TerserPlugin = require('terser-webpack-plugin');

    const full_config = merge(config, {
      mode: 'production',
      devtool: false,
      optimization: {
        minimize: true,
        minimizer: [
          new TerserPlugin({
            terserOptions: {
              ecma: 2020,
              mangle: {
                reserved:[
                  'Drupal',
                  'drupalSettings'
                ]
              },
              format: {
                comments: false,
              },
            },
            extractComments: false,
          }),
        ],
      },
    });

    return full_config;

  }

  if (argv.mode === 'development') {
    const SourceMapDevToolPlugin = require('webpack/lib/SourceMapDevToolPlugin');

    const full_config = merge(config, {
      mode: 'development',
      devtool: 'eval-source-map',
      plugins: [
        new SourceMapDevToolPlugin({
          filename: '[file].map',
          exclude: [/node_modules/, /images/, /spritemap/, /svg-sprites/],
        })
      ]
    });

    return full_config;

  }
};
