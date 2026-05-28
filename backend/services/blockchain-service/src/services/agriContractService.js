const { ethers } = require('ethers');
const config = require('../config');
const contractInteractionService = require('./contractInteractionService');

const AGRI_ABI = [
  'function tokenizeProject(uint256 projectId,string calldata name,uint256 investmentTarget,uint256 totalShares,uint256 pricePerShare,string calldata tokenSymbol)',
  'function recordInvestment(uint256 projectId,uint256 userId,uint256 sharesOwned,uint256 amount)',
  'function registerLease(uint256 projectId,uint256 farmerId,uint256 investmentId,uint256 profitShare,string calldata leaseTerms)',
];

function requireAgriContract() {
  const address = config.contracts.agriInvestment;
  if (!address) {
    throw new Error('Agri investment contract not configured');
  }

  return address;
}

function toUnits(value) {
  return ethers.parseUnits(String(value), 18).toString();
}

class AgriContractService {
  async tokenizeProject(payload) {
    const address = requireAgriContract();
    const result = await contractInteractionService.write({
      address,
      abi: AGRI_ABI,
      method: 'tokenizeProject',
      args: [
        Number(payload.project_id),
        String(payload.project_name),
        toUnits(payload.investment_target),
        Number(payload.total_shares),
        toUnits(payload.price_per_share),
        String(payload.token_symbol || 'EXAFARM'),
      ],
    });

    return {
      ...result,
      project_reference: `project:${payload.project_id}`,
      token_contract_address: address,
    };
  }

  async recordInvestment(payload) {
    const address = requireAgriContract();
    const result = await contractInteractionService.write({
      address,
      abi: AGRI_ABI,
      method: 'recordInvestment',
      args: [
        Number(payload.project_id),
        Number(payload.user_id),
        Number(payload.shares_owned),
        toUnits(payload.investment_amount),
      ],
    });

    return {
      ...result,
      ownership_reference: `investment:${payload.project_id}:${payload.user_id}`,
    };
  }

  async registerLease(payload) {
    const address = requireAgriContract();
    const result = await contractInteractionService.write({
      address,
      abi: AGRI_ABI,
      method: 'registerLease',
      args: [
        Number(payload.project_id),
        Number(payload.farmer_id),
        Number(payload.investment_id || 0),
        Number(payload.profit_share),
        String(payload.lease_terms),
      ],
    });

    return {
      ...result,
      contract_reference: `lease:${payload.project_id}:${payload.farmer_id}`,
    };
  }
}

module.exports = new AgriContractService();
