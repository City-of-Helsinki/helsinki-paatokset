const isDev = process.env.NODE_ENV !== "production";

const path = require('path');
const glob = require('glob');
const globImporter = require('node-sass-glob-importer');

const CleanWebpackPlugin = require('clean-webpack-plugin');
const CopyPlugin = require('copy-webpack-plugin');
const FriendlyErrorsWebpackPlugin = require('@nuxt/friendly-errors-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');
const SVGSpritemapPlugin = require('svg-spritemap-webpack-plugin')

// Handle entry points.
const Entries = () => {
  let entries = {
    styles: ['./src/scss/styles.scss'],
  };

  const matches = glob.sync('./src/js/**/*.js');

  if(matches) {
    entries.bundle = matches;
  }

  return entries;
};

module.exports = {
  entry() {
    return Entries();
  },
  output: {
    path: path.resolve(__dirname, 'dist'),
    chunkFilename: 'js/async/[name].chunk.js',
    pathinfo: true,
    filename: 'js/[name].min.js',
    publicPath: '../',
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
        test: /\.(woff|ttf|eot|svg)$/,
        include: [
          path.resolve(__dirname, 'src/fonts')
        ],
        generator: {
          filename: 'fonts/[name][ext]'
        },
        type: 'asset/resource',
      },
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: ['babel-loader'],
        type: 'javascript/auto',
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
              sassOptions: {
                importer: globImporter()
              },
            },
          },
        ],
        type: 'javascript/auto',
      },
    ],
  },
  resolve: {
    modules: [
      path.join(__dirname, 'node_modules'),
    ],
    extensions: ['.js', '.json'],
  },
  plugins: [
    new FriendlyErrorsWebpackPlugin(),
    new RemoveEmptyScriptsPlugin(),
    new CleanWebpackPlugin(['dist'], {
      root: path.resolve(__dirname),
    }),
    new SVGSpritemapPlugin([
      path.resolve(__dirname, 'src/icons/**/*.svg'),
    ], {
      output: {
        filename: './icons/sprite.svg',
        svg: {
          sizes: false
        }
      },
      sprite: {
        prefix: false,
        gutter: 0,
        generate: {
          title: false,
          symbol: true,
          use: true,
          view: '-view'
        }
      },
    }),
    new MiniCssExtractPlugin({
      filename: 'css/[name].min.css',
    }),
  ],
  watchOptions: {
    aggregateTimeout: 300,
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
