using CodeRED.Plugin.Anime.Api;
using MediaBrowser.Common.Plugins;
using Microsoft.Extensions.DependencyInjection;

namespace CodeRED.Plugin.Anime;

public sealed class ServiceRegistrator : IPluginServiceRegistrator
{
    public void RegisterServices(IServiceCollection serviceCollection)
    {
        serviceCollection.AddHttpClient<CodeRedAnimeClient>();
    }
}
