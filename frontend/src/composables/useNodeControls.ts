import type { Ref } from 'vue'
import { api } from '@/utils/api'
import { useToast } from '@/composables/useToast'
import { apiErrorMessage } from '@/utils/errors'
import { useRealTimeStore } from '@/stores/realTime'
import type { Node } from '@/types'

type NodeControlsContext = {
  targetNode: Ref<string>
  selectedLocalNode: Ref<string>
  permConnect: Ref<boolean>
  selectedNode: Ref<string>
  displayedNodes: Ref<Node[]>
  availableNodes: Ref<Node[]>
}

export function useNodeControls(ctx: NodeControlsContext) {
  const realTimeStore = useRealTimeStore()
  const toast = useToast()

  const requireLocalAndTarget = (): boolean => {
    if (!ctx.targetNode.value || !ctx.selectedLocalNode.value) {
      toast.warning('Select a local node and target node first.')
      return false
    }
    return true
  }

  const connect = async () => {
    if (!requireLocalAndTarget()) {
      return
    }

    try {
      const response = await api.post('/nodes/connect', {
        localnode: ctx.selectedLocalNode.value,
        remotenode: ctx.targetNode.value,
        perm: ctx.permConnect.value ? 'on' : null,
      })

      if (response.data.success) {
        const permLabel = ctx.permConnect.value ? ' (permanent)' : ''
        toast.success(`Connected ${ctx.selectedLocalNode.value} → ${ctx.targetNode.value}${permLabel}`)
        await realTimeStore.fetchNodeData()
      } else {
        toast.error(response.data.message || 'Connect failed')
      }
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Connect failed'))
    }
  }

  const disconnect = async () => {
    if (!requireLocalAndTarget()) {
      return
    }

    try {
      const response = await api.post('/nodes/disconnect', {
        localnode: ctx.selectedLocalNode.value,
        remotenode: ctx.targetNode.value,
        perm: null,
      })

      if (response.data.success) {
        toast.success(`Disconnected ${ctx.targetNode.value} from ${ctx.selectedLocalNode.value}`)
        await realTimeStore.fetchNodeData()
      } else {
        toast.error(response.data.message || 'Disconnect failed')
      }
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Disconnect failed'))
    }
  }

  const monitor = async () => {
    if (!requireLocalAndTarget()) {
      return
    }
    try {
      const response = await api.post('/nodes/monitor', {
        localnode: ctx.selectedLocalNode.value,
        remotenode: ctx.targetNode.value,
        perm: null,
      })

      if (response.data.success) {
        toast.success(`Monitoring ${ctx.targetNode.value} on ${ctx.selectedLocalNode.value}`)
        await realTimeStore.fetchNodeData()
      } else {
        toast.error(response.data.message || 'Monitor failed')
      }
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Monitor failed'))
    }
  }

  const localmonitor = async () => {
    if (!requireLocalAndTarget()) {
      return
    }
    try {
      const response = await api.post('/nodes/local-monitor', {
        localnode: ctx.selectedLocalNode.value,
        remotenode: ctx.targetNode.value,
        perm: null,
      })

      if (response.data.success) {
        toast.success(`Local monitor ${ctx.targetNode.value} on ${ctx.selectedLocalNode.value}`)
        await realTimeStore.fetchNodeData()
      } else {
        toast.error(response.data.message || 'Local monitor failed')
      }
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Local monitor failed'))
    }
  }

  const dtmf = async () => {
    const dtmfCommand = prompt('Enter DTMF command:')
    if (!dtmfCommand || dtmfCommand.trim() === '') {
      return
    }

    let nodeToUse: string | null = null
    if (ctx.selectedLocalNode.value) {
      nodeToUse = ctx.selectedLocalNode.value
    } else if (ctx.selectedNode.value) {
      nodeToUse = String(ctx.selectedNode.value).split(',')[0]
    } else if (ctx.displayedNodes.value.length === 1) {
      nodeToUse = String(ctx.displayedNodes.value[0].id)
    } else if (ctx.availableNodes.value.length === 1) {
      nodeToUse = String(ctx.availableNodes.value[0].id)
    }

    if (!nodeToUse) {
      toast.warning('No local node selected. Please select a node first.')
      return
    }

    try {
      const response = await api.post('/nodes/dtmf', {
        localnode: nodeToUse,
        dtmf: dtmfCommand.trim(),
      })

      if (response.data.success) {
        toast.success(`DTMF sent on node ${nodeToUse}`)
        await realTimeStore.fetchNodeData()
      } else {
        toast.error(response.data.message || 'DTMF failed')
      }
    } catch (error) {
      toast.error(apiErrorMessage(error, 'DTMF failed'))
    }
  }

  return {
    connect,
    disconnect,
    monitor,
    localmonitor,
    dtmf,
  }
}
