import path from 'node:path';
import Encore from '@symfony/webpack-encore';

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore.setOutputPath('public/build/')
  .setPublicPath('/build')
  // The whole React app (Feature-Sliced Design under assets/react), mounted by templates/spa.html.twig.
  .addEntry('app', './assets/app.tsx')
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  // Babel 8's React preset otherwise picks dev mode from BABEL_ENV/NODE_ENV (unset during "encore production") and
  // emits jsxDEV calls, which React's production build doesn't have.
  .enableReactPreset((options) => {
    options.development = !Encore.isProduction();
    options.runtime = 'automatic';
  })
  // .ts/.tsx compile through Babel; types are checked apart, by `npm run typecheck`.
  .enableBabelTypeScriptPreset()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .configureBabel((config) => {
    config.plugins.push([
      'polyfill-corejs3',
      {method: 'usage-global', version: '3.49'},
    ]);
  })
  .addAliases({'@': path.resolve(import.meta.dirname, 'assets/react')});

export default await Encore.getWebpackConfig();
